<?php
// ============================================================
// দুর্গাপুজো লোগো ডায়াগনস্টিক টুল (v3 — স্মার্ট ফোল্ডার ডিটেকশন)
// ব্যবহার: https://আপনার-সাইট.com/festival_check.php খুলুন
// ⚠ কাজ শেষ হলে এই ফাইলটি অবশ্যই ডিলিট করবেন!
// ============================================================
date_default_timezone_set('Asia/Kolkata');
header('Content-Type: text/html; charset=UTF-8');
$today = date('Y-m-d');

// index.php-এর মতোই: সব সম্ভাব্য লোকেশনে "festivals" ফোল্ডার খোঁজা হয়
$candidates = ['uploads/festivals', 'festivals', 'uploads/festival', 'festival'];
$detected = '';
foreach ($candidates as $c) { if (is_dir(__DIR__ . '/' . $c)) { $detected = $c; break; } }

$logos   = ['durgapujo.png', 'maha_shashthi.png', 'maha_saptami.png', 'maha_ashtami.png', 'maha_navami.png', 'bijoya_dashami.png'];
// bengali_calendar.php-এর অন্যান্য উৎসবের লোগো (না থাকলেও সাইট চলবে — ফল-ব্যাক আছে)
$other_logos = ['saraswati.png', 'dol_yatra.png', 'dol_purnima.png', 'buddha_purnima.png', 'rath_yatra.png', 'janmashtami.png', 'mahalaya.png', 'durga_ashtami.png', 'durga_nabami.png', 'durga_puja.png', 'lakshmi_puja.png', 'kali_puja.png', 'diwali.png', 'bhai_phonta.png', 'new_year.png', 'netaji.png', 'republic_day.png', 'pohela_falgun.png', 'chaitra_sankranti.png', 'pohela_boishakh.png', 'rabindra_jayanti.png', 'independence_day.png', 'christmas.png', 'new_year_eve.png'];

$festResolve = function ($file) use ($detected, $candidates) {
    $bases = array_values(array_unique(array_merge([$detected], $candidates)));
    foreach ($bases as $b) {
        if ($b === '') continue;
        $full = $b . '/' . $file;
        if (file_exists(__DIR__ . '/' . $full)) return $full;
    }
    return '';
};
?>
<!DOCTYPE html>
<html lang="bn"><head><meta charset="UTF-8"><title>Festival Logo Check v3</title></head>
<body style="font-family:'Hind Siliguri',sans-serif;max-width:760px;margin:30px auto;padding:0 16px;color:#1a1a1a">
<h2 style="color:#B71C1C">এমব্লেম লোগো ডায়াগনস্টিক (v3)</h2>
<p><b>আজকের তারিখ:</b> <?= $today ?> (Asia/Kolkata)<br>
<b>PHP ভার্সন:</b> <?= PHP_VERSION ?><br>
<b>index.php-এর ফোল্ডার:</b> <?= htmlspecialchars(__DIR__) ?></p>
<?php if ($detected !== '') { ?>
    <div style="padding:12px;border-radius:6px;background:#e8f5e9;margin:10px 0">
        ✅ <b>ফোল্ডার পাওয়া গেছে:</b> <code><?= htmlspecialchars($detected) ?></code>
    </div>
<?php } else { ?>
    <div style="padding:12px;border-radius:6px;background:#ffebee;margin:10px 0">
        ❌ <b>কোথাও "festivals" ফোল্ডার পাওয়া যায়নি!</b><br>
        নিচের যেকোনো একটি জায়গায় ফোল্ডারটি রাখুন (যেকোনোটাই চলবে):<br>
        ① <code><?= htmlspecialchars(__DIR__) ?>\uploads\festivals\</code><br>
        ② <code><?= htmlspecialchars(__DIR__) ?>\festivals\</code> ← সবচেয়ে সহজ
    </div>
<?php } ?>
<?php
$allOk = true;
echo '<h3>লোগো ফাইল (৬টি)</h3>';
foreach ($logos as $f) {
    $p = $festResolve($f);
    $ok = $p !== '';
    if (!$ok) $allOk = false;
    echo '<div style="padding:8px 12px;margin:6px 0;border-radius:6px;background:' . ($ok ? '#e8f5e9' : '#ffebee') . '">'
       . ($ok ? '[OK]' : '[MISSING]') . ' ' . htmlspecialchars($p !== '' ? $p : 'festivals/' . $f) . '</div>';
    if ($ok) echo '<img src="' . htmlspecialchars($p) . '?t=' . time() . '" style="max-width:200px;max-height:140px;border:1px solid #ccc;margin:0 8px 8px 0">';
}
echo '<h3>অন্যান্য উৎসবের লোগো (২৪টি — bengali_calendar.php সেট)</h3>';
foreach ($other_logos as $f) {
    $p = $festResolve($f);
    $ok = $p !== '';
    echo '<div style="padding:6px 12px;margin:4px 0;border-radius:6px;background:' . ($ok ? '#e8f5e9' : '#fff8e1') . '">'
       . ($ok ? '[OK]' : '[ঐচ্ছিক — নেই]') . ' ' . htmlspecialchars($p !== '' ? $p : 'festivals/' . $f) . '</div>';
}
echo '<h3>ফোল্ডার রিপোর্ট</h3>';
foreach ($candidates as $d) {
    $exists = is_dir(__DIR__ . '/' . $d);
    echo '<div style="padding:8px 12px;margin:6px 0;border-radius:6px;background:' . ($exists ? '#e8f5e9' : '#f5f5f5') . '">'
       . ($exists ? '✓ আছে:' : '— নেই:') . ' ' . htmlspecialchars($d) . '</div>';
    if ($exists) {
        $files = [];
        foreach (scandir(__DIR__ . '/' . $d) as $x) { if ($x !== '.' && $x !== '..') $files[] = $x; }
        echo '<pre style="background:#f4f1eb;padding:10px">' . htmlspecialchars(implode("\n", $files)) . '</pre>';
    }
}
echo '<h3 style="color:' . ($allOk ? '#1b5e20' : '#B71C1C') . '">' . ($allOk ? '🎉 সব ফাইল ঠিক আছে! এখন হোমপেজ রিফ্রেশ করুন (Ctrl+F5)' : 'উপরের [MISSING] ফাইলগুলো ঠিক করুন') . '</h3>';
?>
<p style="background:#fff3e0;padding:10px;border-radius:6px"><b>নিরাপত্তা নোট:</b> কাজ শেষ হলে এই <b>festival_check.php</b> ফাইলটি সার্ভার থেকে ডিলিট করে দিন!</p>
</body></html>
