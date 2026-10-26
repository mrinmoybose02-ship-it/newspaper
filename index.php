<?php
// Prevent browser caching so new news shows up immediately on live server
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require_once __DIR__ . '/config.php';

// Set timezone to IST (Indian Standard Time)
date_default_timezone_set('Asia/Kolkata');

// FIX: Sync MySQL session timezone to IST to match PHP timezone
try {
    if (isset($pdo)) {
        $pdo->exec("SET time_zone = '+05:30';");
    }
} catch (Exception $e) {}

// Determine current language for UI display
 $current_lang = 'বাংলা';

// ====== HD-QUALITY TTS PROXY (Bengali Female Voice) ======
// Quality priority (best -> fallback):
//   1. Azure Neural TTS  -> bn-IN-TanishaaNeural (HD neural female, 24kHz)
//                          [requires AZURE_TTS_KEY in tts_config.php]
//   2. Google TTS gtx    -> cleaner audio path vs tw-ob
//   3. Google TTS tw-ob  -> original fallback
// Audio is cached to disk so repeated Bengali chunks play instantly.
if (isset($_GET['tts_proxy'])) {
    @ini_set('display_errors', '0');
    @error_reporting(0);
    while (ob_get_level() > 0) { @ob_end_clean(); }

    $text = isset($_GET['text']) ? trim($_GET['text']) : '';
    if ($text === '') { http_response_code(400); exit; }
    // FIX: mb_substr for Bengali UTF-8 (no half-character cuts)
    // Google TTS reliably supports up to ~200 chars per request.
    $text = mb_substr($text, 0, 200, 'UTF-8');

    // ===== AUDIO CACHE (24h) -- repeated Bengali chunks play instantly =====
    $cacheDir = __DIR__ . '/cache_tts';
    if (!is_dir($cacheDir)) { @mkdir($cacheDir, 0775, true); }
    $cacheKey = 'bn_' . md5($text);
    $cacheFile = $cacheDir . '/' . $cacheKey . '.mp3';

    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 86400)) {
        header('Content-Type: audio/mpeg');
        header('Cache-Control: public, max-age=86400, immutable');
        header('Content-Length: ' . filesize($cacheFile));
        readfile($cacheFile);
        exit;
    }
    // ============================================================

    $audioData = null;

    // ============================================================
    //   TTS API KEYS — edit directly here (no external file needed)
    // ============================================================
    //   Priority order:
    //     1) ElevenLabs "Tuli" (HD Bengali female)  — if ELEVENLABS_API_KEY set
    //     2) Google TTS gtx    (free, no key)        — fallback
    //     3) Google TTS tw-ob (free, no key)         — last-resort fallback
    //
    //   Leave '' to disable ElevenLabs. Google TTS works with zero config.
    // ============================================================

    // ----- ElevenLabs: Tuli (HD Bengali female voice) -----
    //   1. Get API key:  https://elevenlabs.io → Profile → API Keys
    //   2. Find "Tuli":  https://elevenlabs.io/voice-library → search "Tuli"
    //   3. Click on it → copy the voice_id (looks like "21m00Tcm4TlvDq8ikWAM")
    //   4. Paste both values below.
    //
    //   Free tier: 10,000 chars/month. Paid plans from $5/mo (30k chars).
    $ELEVENLABS_API_KEY   = '';                              // ← paste ElevenLabs API key
    $ELEVENLABS_VOICE_ID  = '';                              // ← paste Tuli's voice_id
    $ELEVENLABS_MODEL     = 'eleven_multilingual_v2';        // supports Bengali natively
    $ELEVENLABS_STABILITY = 0.50;                            // 0..1 (lower = more variable)
    $ELEVENLABS_SIMILAR   = 0.75;                            // 0..1 (voice similarity boost)

    // ====== 1. ElevenLabs "Tuli" — HD Bengali female voice ======
    if (!empty($ELEVENLABS_API_KEY) && !empty($ELEVENLABS_VOICE_ID)) {
        $audioData = ttsElevenLabs(
            $text, $ELEVENLABS_API_KEY, $ELEVENLABS_VOICE_ID,
            $ELEVENLABS_MODEL, $ELEVENLABS_STABILITY, $ELEVENLABS_SIMILAR
        );
    }

    // ====== 2. Google TTS via client=gtx (free fallback) ======
    if (empty($audioData)) {
        $audioData = ttsGoogleTranslate($text, 'gtx');
    }

    // ====== 3. Google TTS via client=tw-ob (last-resort fallback) ======
    if (empty($audioData)) {
        $audioData = ttsGoogleTranslate($text, 'tw-ob');
    }

    if (!empty($audioData)) {
        // Save to cache for instant replay (24h)
        @file_put_contents($cacheFile, $audioData, LOCK_EX);
        header('Content-Type: audio/mpeg');
        header('Cache-Control: public, max-age=86400, immutable');
        header('Content-Length: ' . strlen($audioData));
        echo $audioData;
    } else {
        http_response_code(502);
    }
    exit;
}

/**
 * ElevenLabs -- HD Bengali female voice (default: "Tuli" via voice_id).
 * https://elevenlabs.io/docs/api-reference/text-to-speech
 *
 * Request:  POST https://api.elevenlabs.io/v1/text-to-speech/{voice_id}
 *           Header: xi-api-key: <key>
 *           Body JSON: {
 *             "text":          "...",
 *             "model_id":      "eleven_multilingual_v2",   // supports Bengali
 *             "voice_settings": {
 *               "stability":         0.50,
 *               "similarity_boost":  0.75,
 *               "style":             0.00,
 *               "use_speaker_boost": true
 *             }
 *           }
 * Response: audio stream (audio/mpeg) — MP3 bytes returned directly.
 *
 * Voice ID examples (replace with Tuli's actual voice_id from Voice Library):
 *   Rachel       : 21m00Tcm4TlvDq8ikWAM   (default female, conversational)
 *   Tuli         : <find in https://elevenlabs.io/voice-library by searching "Tuli">
 */
function ttsElevenLabs($text, $apiKey, $voiceId,
                       $model = 'eleven_multilingual_v2',
                       $stability = 0.50, $similarity = 0.75) {
    if (empty($apiKey) || empty($voiceId) || empty($text)) return null;

    $url = 'https://api.elevenlabs.io/v1/text-to-speech/' . urlencode($voiceId);

    $payload = [
        'text'      => $text,
        'model_id'  => $model ?: 'eleven_multilingual_v2',
        'voice_settings' => [
            'stability'         => (float)$stability,
            'similarity_boost'  => (float)$similarity,
            'style'             => 0.0,
            'use_speaker_boost' => true,
        ],
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL             => $url,
        CURLOPT_POST            => true,
        CURLOPT_RETURNTRANSFER  => true,
        CURLOPT_SSL_VERIFYPEER  => false,
        CURLOPT_TIMEOUT         => 15,
        CURLOPT_HTTPHEADER      => [
            'xi-api-key: ' . $apiKey,
            'Content-Type: application/json',
            'Accept: audio/mpeg',          // we want MP3 audio back
            'User-Agent: SangbadSankalanTTS/1.0',
        ],
        CURLOPT_POSTFIELDS      => json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);
    $data = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ct   = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    // ElevenLabs returns audio/mpeg on success; JSON error object on failure.
    if ($code === 200 && !empty($data) && stripos($ct, 'audio') !== false) {
        return $data;
    }
    return null;
}

/**
 * Google Translate TTS -- Bengali female voice.
 * client=gtx usually returns a cleaner MP3 than tw-ob.
 */
function ttsGoogleTranslate($text, $client = 'gtx') {
    $textLen = mb_strlen($text, 'UTF-8');
    $url = 'https://translate.google.com/translate_tts?ie=UTF-8'
         . '&q=' . rawurlencode($text)
         . '&tl=bn'
         . '&client=' . $client
         . '&total=1'
         . '&idx=0'
         . '&textlen=' . $textLen
         . '&prev=input';

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 6,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => [
            'Referer: https://translate.google.com/',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
            'Accept: audio/mpeg, audio/*;q=0.9, */*;q=0.5',
            'Accept-Language: en-US,en;q=0.5',
            'Accept-Encoding: identity',
            'Connection: keep-alive',
        ],
    ]);
    $data = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ct = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    if ($code === 200 && !empty($data) && stripos($ct, 'audio') !== false) {
        return $data;
    }
    return null;
}
// =====================================================================

// রিয়েল-টাইম নিউজ আনার জন্য API
if (isset($_GET['action']) && $_GET['action'] === 'api_latest_news') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $stmt = $pdo->prepare("SELECT id, title, slug, image FROM news WHERE status='published' AND created_at <= NOW() ORDER BY created_at DESC LIMIT 1");
        $stmt->execute();
        $latest = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($latest ?: ["error" => "no_news"]);
    } catch (Exception $e) {
        echo json_encode(["error" => "db_error"]);
    }
    exit;
}

// ====== BACKGROUND AJAX FETCH FOR NSE STOCKS TO SPEED UP PAGE LOAD ======
if (isset($_GET['ajax_nse'])) {
    header('Content-Type: application/json');
    $nseStockList = [
        ['s'=>'RELIANCE.NS','short'=>'RELIANCE'],['s'=>'TCS.NS','short'=>'TCS'],['s'=>'INFY.NS','short'=>'INFY'],
        ['s'=>'HDFCBANK.NS','short'=>'HDFCBANK'],['s'=>'ICICIBANK.NS','short'=>'ICICIBANK'],['s'=>'SBIN.NS','short'=>'SBIN'],
        ['s'=>'KOTAKBANK.NS','short'=>'KOTAKBANK'],['s'=>'AXISBANK.NS','short'=>'AXISBANK'],['s'=>'INDUSINDBK.NS','short'=>'INDUSINDBK'],
        ['s'=>'WIPRO.NS','short'=>'WIPRO'],['s'=>'HCLTECH.NS','short'=>'HCLTECH'],['s'=>'TECHM.NS','short'=>'TECHM'],
        ['s'=>'BHARTIARTL.NS','short'=>'BHARTIARTL'],['s'=>'ITC.NS','short'=>'ITC'],['s'=>'LT.NS','short'=>'LT'],
        ['s'=>'HINDUNILVR.NS','short'=>'HINDUNILVR'],['s'=>'NESTLEIND.NS','short'=>'NESTLEIND'],['s'=>'BRITANNIA.NS','short'=>'BRITANNIA'],
        ['s'=>'TATACONSUM.NS','short'=>'TATACONSUM'],['s'=>'VARUNBEVERAGES.NS','short'=>'VARUNBEV'],['s'=>'BAJFINANCE.NS','short'=>'BAJFINANCE'],
        ['s'=>'BAJAJFINSV.NS','short'=>'BAJAJFINSV'],['s'=>'MARUTI.NS','short'=>'MARUTI'],['s'=>'M&M.NS','short'=>'M&M'],
        ['s'=>'EICHERMOT.NS','short'=>'EICHERMOT'],['s'=>'HEROMOTOCO.NS','short'=>'HEROMOTOCO'],['s'=>'TATAMOTORS.NS','short'=>'TATAMOTORS'],
        ['s'=>'ASHOKLEY.NS','short'=>'ASHOKLEY'],['s'=>'ADANIENT.NS','short'=>'ADANIENT'],['s'=>'ADANIPORTS.NS','short'=>'ADANIPORTS'],
        ['s'=>'SUNPHARMA.NS','short'=>'SUNPHARMA'],['s'=>'DRREDDY.NS','short'=>'DRREDDY'],['s'=>'CIPLA.NS','short'=>'CIPLA'],
        ['s'=>'DIVISLAB.NS','short'=>'DIVISLAB'],['s'=>'APOLLOHOSP.NS','short'=>'APOLLOHOSP'],['s'=>'ASIANPAINT.NS','short'=>'ASIANPAINT'],
        ['s'=>'TITAN.NS','short'=>'TITAN'],['s'=>'TATASTEEL.NS','short'=>'TATASTEEL'],['s'=>'JSWSTEEL.NS','short'=>'JSWSTEEL'],
        ['s'=>'HINDALCO.NS','short'=>'HINDALCO'],['s'=>'VEDL.NS','short'=>'VEDL'],['s'=>'NTPC.NS','short'=>'NTPC'],
        ['s'=>'POWERGRID.NS','short'=>'POWERGRID'],['s'=>'ONGC.NS','short'=>'ONGC'],['s'=>'COALINDIA.NS','short'=>'COALINDIA'],
        ['s'=>'GAIL.NS','short'=>'GAIL'],['s'=>'IOC.NS','short'=>'IOC'],['s'=>'GRASIM.NS','short'=>'GRASIM'],
        ['s'=>'ULTRACEMCO.NS','short'=>'ULTRACEMCO'],['s'=>'SHREECEM.NS','short'=>'SHREECEM'],['s'=>'IRCTC.NS','short'=>'IRCTC'],
        ['s'=>'PIDILITIND.NS','short'=>'PIDILITIND'],['s'=>'DABUR.NS','short'=>'DABUR'],['s'=>'AMBUJACEM.NS','short'=>'AMBUJACEM'],['s'=>'SAIL.NS','short'=>'SAIL']
    ];
    $nseCacheFile = __DIR__ . '/cache_nse_stocks.json';
    $nseData = [];
    
    // Return cached data if fresh (prevents spamming Yahoo API on rapid page loads)
    if (file_exists($nseCacheFile) && (time() - filemtime($nseCacheFile) < 300)) { 
        $nseCached = json_decode(file_get_contents($nseCacheFile), true); 
        if (is_array($nseCached)) {
            echo json_encode(['status' => 'cached', 'data' => $nseCached]);
            exit;
        }
    }

    if (function_exists('curl_init')) {
        foreach ($nseStockList as $st) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => "https://query1.finance.yahoo.com/v8/finance/chart/".$st['s']."?range=1d&interval=1d",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 2, 
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_HTTPHEADER => ['User-Agent: Mozilla/5.0'],
                CURLOPT_FOLLOWLOCATION => true
            ]);
            $resp = curl_exec($ch);
            $hc = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $p=0; $c=0; $cp=0; $ok=false;
            if($resp && $hc===200){
                $j=json_decode($resp,true);
                if(isset($j['chart']['result'][0]['meta'])){
                    $m=$j['chart']['result'][0]['meta'];
                    if(isset($m['regularMarketPrice'])){
                        $p=round($m['regularMarketPrice'],2);
                        $pc2=$m['chartPreviousClose']??$p;
                        $c=round($p-$pc2,2);
                        $cp=$pc2>0?round(($c/$pc2)*100,2):0;
                        $ok=true;
                    }
                }
            }
            $nseData[]=['short'=>$st['short'],'price'=>$p,'change'=>$c,'changePct'=>$cp,'fetched'=>$ok];
        }
        @file_put_contents($nseCacheFile, json_encode($nseData), LOCK_EX);
    } else {
        foreach ($nseStockList as $st) {
            $nseData[] = ['short'=>$st['short'],'price'=>0,'change'=>0,'changePct'=>0,'fetched'=>false];
        }
    }
    echo json_encode(['status' => 'fresh', 'data' => $nseData]);
    exit;
}
// =====================================================

// ====== AJAX ENDPOINT FOR NEW NEWS NOTIFICATION & AUTO FETCH ======
if (isset($_GET['ajax_check_new_news'])) {
    // ★ v6.8 FIX: PHP warning/notice রেসপন্সে ঢুকে গেলে ব্রাউজারের JSON.parse ফেল করে এবং
    // নতুন খবর এলেও নোটিফিকেশন চুপচাপ মিস হয়ে যেত — তাই রেসপন্স সম্পূর্ণ পরিষ্কার করা হচ্ছে।
    @ini_set('display_errors', '0');
    @error_reporting(0);
    while (ob_get_level() > 0) { @ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, max-age=0');
    $last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
    try {
        $stmt = $pdo->prepare("SELECT n.id, n.title, n.excerpt, n.image, n.created_at, n.views, c.name as category_name, sc.name as subcategory_name 
                               FROM news n 
                               LEFT JOIN categories c ON n.category_id = c.id 
                               LEFT JOIN (SELECT news_id, subcategory_id FROM news_subcategories GROUP BY news_id) nsc ON n.id = nsc.news_id 
                               LEFT JOIN subcategories sc ON nsc.subcategory_id = sc.id 
                               WHERE n.status = 'published' AND n.id > ? AND n.created_at <= NOW() 
                               ORDER BY n.id ASC");
        $stmt->execute([$last_id]);
        $newNewsList = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($newNewsList)) {
            $maxId = $last_id;
            $placeholderImg = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='400' height='250'%3E%3Crect width='400' height='250' fill='%23e8e4de'/%3E%3Ctext x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' fill='%23999' font-family='sans-serif' font-size='14'%3E%E0%A6%9B%E0%A6%AC%E0%A6%BF%3C/text%3E%3C/svg%3E";
            
            foreach($newNewsList as &$n) {
                if($n['id'] > $maxId) $maxId = (int)$n['id'];
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
            }
            echo json_encode(['status' => 'new', 'last_id' => $maxId, 'items' => $newNewsList]);
        } else {
            echo json_encode(['status' => 'no_new']);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error']);
    }
    exit;
}
// =====================================================

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
        if ($diff < 86400) { $h = (int)($diff / 3600); return str_replace($en, $bn, $h) . ' ঘণ্টা আগে'; }
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
        if (preg_match('#https?://(?:www\.)?(?:twitter|x)\.com/(?:[^/]+/status|i/web/status)/([0-9]+)#i', $url, $m)) {
            return 'https://platform.twitter.com/embed/Tweet.html?id=' . $m[1];
        }
        if (strpos($url, 'youtube.com/embed/') !== false || strpos($url, 'player.vimeo.com') !== false || strpos($url, 'platform.twitter.com') !== false) return $url;
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
        if (preg_match('#https?://(?:www\.)?(?:twitter|x)\.com/(?:[^/]+/status|i/web/status)/([0-9]+)#i', $url, $m)) {
            $url = 'https://platform.twitter.com/embed/Tweet.html?id=' . $m[1];
        }
        if (strpos($url, 'http://') !== 0 && strpos($url, 'https://') !== 0) {
            if (strpos($url, '//') === 0) { $url = 'https:' . $url; } else { $url = 'https://' . $url; }
        }
        if (strpos($url, 'youtube.com/embed/') !== false || strpos($url, 'player.vimeo.com/video/') !== false || strpos($url, 'platform.twitter.com/embed/') !== false) {
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
            $html .= '<img src="' . htmlspecialchars($ad['image']) . '" alt="' . htmlspecialchars($ad['title']) . '" style="max-width:100%;height:auto;border-radius:8px;" loading="lazy" decoding="async">';
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

 $otherAdminPages = ['admin_dashboard','admin_add','admin_edit','admin_manage','admin_breaking','admin_categories','admin_cat_edit','admin_password','admin_epaper'];
if (in_array($page, $otherAdminPages)) {
    if (!isset($_SESSION['user_id'])) { header('Location: ?page=admin_login'); exit; }
    $filePath = __DIR__ . '/' . $page . '.php';
    if (file_exists($filePath)) { require_once $filePath; exit; }
    header('Location: ?page=admin_dashboard'); exit;
}

try { $pdo->exec("DELETE t1 FROM categories t1 INNER JOIN categories t2 WHERE t1.name = t2.name AND t1.id > t2.id"); } catch(PDOException $e) {}

 $defaultCategories = ['প্রধান খবর','জাতীয়','রাজনীতি','আন্তর্জাতিক','অর্থনীতি','খেলাধুলা','বিনোদন','শিক্ষা','প্রযুক্তি','স্বাস্থ্য','বিশেষ সংবাদ','লাইফস্টাইল','ধর্ম','সংস্কৃতি','মতামত','ক্রাইম','কৃষি','ভ্রমণ','চাকরি','আর্কাইভ'];
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

// ====== AUTO-MOVE OLD NEWS TO ARCHIVE CATEGORY ======
try {
    $stmtArchCat = $pdo->prepare("SELECT id FROM categories WHERE name = 'আর্কাইভ' LIMIT 1");
    $stmtArchCat->execute();
    $archiveCatId = $stmtArchCat->fetchColumn();
    if ($archiveCatId) {
        $stmtMove = $pdo->prepare("UPDATE news SET category_id = ? WHERE created_at < (NOW() - INTERVAL 30 DAY) AND created_at <= NOW() AND status = 'published' AND category_id != ?");
        $stmtMove->execute([$archiveCatId, $archiveCatId]);
    }
} catch (Exception $e) {}
// =====================================================

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

// Date Limit Logic ★ v6.7: প্রতিটি খবর ৩০ দিন valid — ৩০ দিনের বেশি পুরোনো হলে সেটি শুধুমাত্র
// আর্কাইভে (আর্কাইভ ক্যাটাগরি + আর্কাইভ মাস-ভিউ) দেখাবে। আর্কাইভে ৩০ দিনের কম পুরোনো কোনো খবর
// কখনোই দেখাবে না — অ্যাডমিন ভুলে নতুন খবর আর্কাইভ ক্যাটাগরিতে দিলেও না (30 days na hole আর্কাইভ e jabe na)。
// NEVER show future-dated news.
 $show_all_news = isset($_COOKIE['show_all_news']) && $_COOKIE['show_all_news'] === 'yes';
 $is_archive_cat = ($currentCat === 'আর্কাইভ');
 $is_archive_view = $is_archive_cat || ($archYear > 0); // ?y=&m= মাস-ভিউগুলোও আর্কাইভের অংশ
 $futureLimit = " AND n.created_at <= NOW()";
if ($show_all_news) {
    $pastLimit = ""; // মালিকের escape-hatch cookie: সব খবর সব জায়গায়
} elseif ($is_archive_view) {
    $pastLimit = " AND n.created_at < (NOW() - INTERVAL 30 DAY)"; // আর্কাইভে শুধু ৩০ দিনের বেশি পুরোনো খবর
} else {
    $pastLimit = " AND n.created_at >= (NOW() - INTERVAL 30 DAY)"; // সাধারণ ভিউ: শেষ ৩০ দিনের খবর
}
 $dateLimitSQL = $futureLimit . $pastLimit;

// Archive Dropdown ★ v6.7: শুধু সেই মাসগুলো দেখাবে যেখানে ৩০ দিনের বেশি পুরোনো খবর আছে —
// বর্তমান মাস (এখনো ৩০ দিন হয়নি এমন খবর) কখনো তালিকায় আসবে না
 $archiveDates = [];
try {
    $archStmt = $pdo->query("SELECT YEAR(n.created_at) as y, MONTH(n.created_at) as m FROM news n WHERE n.status = 'published' AND n.created_at < (NOW() - INTERVAL 30 DAY) AND n.created_at <= NOW() GROUP BY y, m ORDER BY y DESC, m DESC");
    $archiveDates = $archStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
 $bnMonths = [1=>'জানুয়ারি', 2=>'ফেব্রুয়ারি', 3=>'মার্চ', 4=>'এপ্রিল', 5=>'মে', 6=>'জুন', 7=>'জুলাই', 8=>'আগস্ট', 9=>'সেপ্টেম্বর', 10=>'অক্টোবর', 11=>'নভেম্বর', 12=>'ডিসেম্বর'];

 $categories = $pdo->query("SELECT MIN(id) as id, name FROM categories GROUP BY name ORDER BY MIN(id) ASC")->fetchAll();
 $catIdMap = [];
foreach ($pdo->query("SELECT id, name FROM categories")->fetchAll() as $c) { $catIdMap[$c['id']] = $c['name']; }

// Breaking News: Valid for 30 days only, no future dates
 $breakingData = array_map(function($b){ return ['id'=>$b['id'],'text'=>$b['text'],'time'=>$b['created_at']]; }, $pdo->query("SELECT * FROM breaking_news WHERE created_at >= (NOW() - INTERVAL 30 DAY) AND created_at <= NOW() ORDER BY created_at DESC")->fetchAll());

// ====== PERFORMANCE FIX: Fetch Latest News ID directly instead of loading all news ======
// ★ v6.6: id + title দুটোই নেওয়া হচ্ছে — পেজ লোডেই সর্বশেষ খবরের পপ-আপ দেখানোর জন্য।
// created_at <= NOW() যোগ করা হয়েছে — ফিউচার-ডেটেড খবর seed হিসেবে ধরা হবে না
// (নাহলে সেটা প্রকাশের সময় নোটিফিকেশন মিস হয়ে যেত)।
$latestNewsId = 0; $latestNewsTitle = '';
try {
    $lnRow = $pdo->query("SELECT id, title FROM news WHERE status='published' AND created_at <= NOW() ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($lnRow) { $latestNewsId = (int)$lnRow['id']; $latestNewsTitle = (string)$lnRow['title']; }
} catch (Exception $e) {}
// ★ v6.6: true = পেজ খুললেই (৪ সেকেন্ড পর) সর্বশেষ খবরের পপ-আপ দেখাবে; false = শুধু নতুন খবর এলে দেখাবে
$NEWS_POPUP_ON_LOAD = true;
$latestNewsTitleJS = json_encode(($latestNewsTitle !== '' ? $latestNewsTitle : 'নতুন খবর এসেছে'), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP);

// ====== PERFORMANCE FIX: Fetch Popular Posts directly with LIMIT ======
 $popularPosts = [];
try {
    $ppStmt = $pdo->query("SELECT n.*, c.name as category_name, sc.name as subcategory_name, sc.id as subcategory_id FROM news n LEFT JOIN categories c ON n.category_id = c.id LEFT JOIN (SELECT news_id, subcategory_id FROM news_subcategories GROUP BY news_id) nsc ON n.id = nsc.news_id LEFT JOIN subcategories sc ON nsc.subcategory_id = sc.id WHERE n.status = 'published' AND n.created_at >= (NOW() - INTERVAL 30 DAY) AND n.created_at <= NOW() ORDER BY n.is_featured DESC, n.created_at DESC LIMIT 6");
    $popularPosts = array_map(function($row) { $row['subcategory'] = $row['subcategory_name'] ?? ''; return $row; }, array_map('safeMapNewsKeys', $ppStmt->fetchAll()));
} catch (Exception $e) {}

// ====== PERFORMANCE FIX: Fetch Category Post Counts directly ======
 $catPostCounts = [];
try {
    $cntStmt = $pdo->query("SELECT c.name, COUNT(n.id) as cnt FROM categories c LEFT JOIN news n ON n.category_id = c.id AND n.status = 'published' GROUP BY c.name");
    foreach ($cntStmt->fetchAll() as $r) { $catPostCounts[$r['name']] = $r['cnt']; }
} catch (Exception $e) {}

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

// ====== PERFORMANCE FIX: Fetch Popular Tags directly (limit to recent 500 to avoid heavy load) ======
 $tagCounts = [];
 $popularTags = [];
try {
    $tagStmt = $pdo->query("SELECT tags FROM news WHERE status='published' AND tags IS NOT NULL AND tags != '' ORDER BY created_at DESC LIMIT 500");
    while($tr = $tagStmt->fetch()) {
        $tags = array_filter(array_map('trim', explode(',', $tr['tags'])));
        foreach ($tags as $tag) { if ($tag !== '') { if (!isset($tagCounts[$tag])) $tagCounts[$tag] = 0; $tagCounts[$tag]++; } }
    }
    arsort($tagCounts); 
    $popularTags = array_slice($tagCounts, 0, 25, true);
} catch (Exception $e) {}
// =====================================================

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
    $stmt = $pdo->prepare("SELECT n.*, c.name as category_name, sc.name as subcategory_name, sc.id as subcategory_id FROM news n LEFT JOIN categories c ON n.category_id = c.id LEFT JOIN (SELECT news_id, subcategory_id FROM news_subcategories GROUP BY news_id) nsc ON n.id = nsc.news_id LEFT JOIN subcategories sc ON nsc.subcategory_id = sc.id WHERE n.id = ? AND n.status = 'published' AND n.created_at <= NOW()");
    $stmt->execute([(int)($_GET['id'] ?? 0)]);
    if ($row = $stmt->fetch()) {
        $row = safeMapNewsKeys($row);
        $row['subcategory'] = $row['subcategory_name'] ?? '';
        $singleNews = $row;
        $singleRelatedTags = !empty($singleNews['tags']) ? array_filter(array_map('trim', explode(',', $singleNews['tags']))) : [];
        $related = [];
        if (!empty($singleRelatedTags)) {
            // PERFORMANCE FIX: Limit tag search to first 2 tags to prevent slow LIKE queries
            $searchTags = array_slice($singleRelatedTags, 0, 2);
            $stmt2 = $pdo->prepare("SELECT n.*, c.name as category_name, sc.name as subcategory_name, sc.id as subcategory_id FROM news n LEFT JOIN categories c ON n.category_id = c.id LEFT JOIN (SELECT news_id, subcategory_id FROM news_subcategories GROUP BY news_id) nsc ON n.id = nsc.news_id LEFT JOIN subcategories sc ON nsc.subcategory_id = sc.id WHERE n.id != ? AND n.status = 'published'{$dateLimitSQL} AND (" . implode(' OR ', array_fill(0, count($searchTags), 'n.tags LIKE ?')) . ") ORDER BY n.created_at DESC LIMIT 6");
            $stmt2->execute(array_merge([$singleNews['id']], array_map(function($t){ return "%$t%"; }, $searchTags)));
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
    }
    $sql .= $dateLimitSQL;
    $countSql .= $dateLimitSQL;

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

 $navCatIcons = ['প্রধান খবর'=>'fa-fire','জাতীয়'=>'fa-flag','রাজনীতি'=>'fa-landmark','আন্তর্জাতিক'=>'fa-globe','অর্থনীতি'=>'fa-chart-line','খেলাধুলা'=>'fa-futbol','বিনোদন'=>'fa-film','শিক্ষা'=>'fa-graduation-cap','প্রযুক্তি'=>'fa-microchip','স্বাস্থ্য'=>'fa-heart-pulse','বিশেষ সংবাদ'=>'fa-star','লাইফস্টাইল'=>'fa-spa','ধর্ম'=>'fa-mosque','সংস্কৃতি'=>'fa-masks-theater','মতামত'=>'fa-comment-dots','ক্রাইম'=>'fa-gavel','কৃষি'=>'fa-seedling','ভ্রমণ'=>'fa-plane','চাকরি'=>'fa-briefcase','আর্কাইভ'=>'fa-clock-rotate-left'];

 $bnDays = ['Sunday'=>'রবিবার','Monday'=>'সোমবার','Tuesday'=>'মঙ্গলবার','Wednesday'=>'বুধবার','Thursday'=>'বৃহস্পতিবার','Friday'=>'শুক্রবার','Saturday'=>'শনিবার'];

 $todayStr = date('d F Y') . ', ' . date('l');

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

// ====== FETCH ALL POPUP ADS (popup, popup_1, popup_2, popup_3, etc.) ======
 $popupAdsArr = [];
try {
    $stmtPop = $pdo->prepare("SELECT * FROM advertisements WHERE position LIKE 'popup%' AND is_active = 1 AND (start_date IS NULL OR start_date <= CURDATE()) AND (end_date IS NULL OR end_date >= CURDATE()) ORDER BY id DESC");
    $stmtPop->execute();
    $popupAdsArr = $stmtPop->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
 $adPopup = '';
foreach ($popupAdsArr as $pad) { $adPopup .= renderAd($pad); }
// =========================================================================

// ====== INSTANTLY LOAD NSE DATA FROM CACHE (NO SERVER BLOCKING) ======
 $nseCacheFile = __DIR__ . '/cache_nse_stocks.json';
 $nseData = [];
if (file_exists($nseCacheFile)) { 
    $nseCached = json_decode(@file_get_contents($nseCacheFile), true); 
    if (is_array($nseCached)) $nseData = $nseCached; 
}
// =====================================================

 $placeholderImg = "data:image/svg+xml," . urlencode("<svg xmlns='http://www.w3.org/2000/svg' width='400' height='250'><rect width='400' height='250' fill='%23e8e4de'/><text x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' fill='%23999' font-family='sans-serif' font-size='14'>ছবি</text></svg>");
 $placeholderImgSm = "data:image/svg+xml," . urlencode("<svg xmlns='http://www.w3.org/2000/svg' width='120' height='90'><rect width='120' height='90' fill='%23e8e4de'/><text x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' fill='%23999' font-family='sans-serif' font-size='10'>ছবি</text></svg>");

 $breakingItems = []; foreach ($breakingData as $bi) { $breakingItems[] = '<span>' . htmlspecialchars($bi['text']) . '</span>'; }
 $breakingTrackHTML = !empty($breakingItems) ? implode('', $breakingItems) . implode('', $breakingItems) : '';
 $nseItems = []; foreach ($nseData as $ni) { if (!$ni['fetched']) continue; $cls = $ni['change'] >= 0 ? 'nse-up' : 'nse-down'; $sign = $ni['change'] >= 0 ? '+' : ''; $nseItems[] = '<div class="nse-si"><span class="nse-sym">' . htmlspecialchars($ni['short']) . '</span><span class="nse-pr">' . number_format($ni['price'], 2) . '</span><span class="nse-chg ' . $cls . '">' . $sign . number_format($ni['change'], 2) . ' (' . $sign . number_format($ni['changePct'], 2) . '%)</span></div>'; }
 $nseTrackHTML = !empty($nseItems) ? implode('', $nseItems) . implode('', $nseItems) : '';

// ====== FESTIVAL LOGIC (v4: bengali_calendar.php কানেক্টেড + smart folder auto-detect) ======
// তারিখ অনুযায়ী প্রতিদিনের সঠিক লোগো + গ্রিটিং ব্যানার অটোমেটিক দেখায়।
// ⚠ প্রতি বছর শুধু নিচের তারিখগুলো আপডেট করলেই হবে (ফরম্যাট: 'YYYY-MM-DD')।
// ★ স্মার্ট ফোল্ডার ডিটেকশন (v3): "festivals" ফোল্ডারটি যেখানেই রাখুন না কেন, কোড নিজেই খুঁজে নেবে।
//    সমর্থিত লোকেশন (ক্রমানুসারে খোঁজা হয়):
//    1) uploads/festivals/              ← আদর্শ লোকেশন
//    2) festivals/                      ← সাইট রুটে সরাসরি (index.php-এর পাশে) রাখলেও চলবে
//    3) uploads/festival/ বা festival/  ← একবচন নাম হলেও চলবে
$FEST_CANDIDATES = ['uploads/festivals', 'festivals', 'uploads/festival', 'festival'];
$FEST_BASE = '';
foreach ($FEST_CANDIDATES as $__fc) {
    if (is_dir(__DIR__ . '/' . $__fc)) { $FEST_BASE = $__fc; break; }
}
$FEST_BANNER_BASE = '';
foreach ($FEST_CANDIDATES as $__fc) {
    if (is_dir(__DIR__ . '/' . $__fc . '/banners')) { $FEST_BANNER_BASE = $__fc; break; }
}
if ($FEST_BASE === '')        $FEST_BASE        = 'uploads/festivals'; // কিছু পাওয়া না গেলে আদর্শ পাথ ধরে রাখা হয়
if ($FEST_BANNER_BASE === '') $FEST_BANNER_BASE = $FEST_BASE;

// ★ v4: bengali_calendar.php কানেকশন — এই একটি ফাইলেই সব উৎসবের তারিখ ও লোগোর তালিকা থাকে।
//   ফাইলটি index.php-এর পাশে (সাইট রুটে) রাখলেই সব উৎসব অটোমেটিক কাজ করবে;
//   ফাইল না থাকলেও সমস্যা নেই — নিচের হাতে-লেখা $FESTIVAL_LOGOS তালিকা আগের মতোই চলবে।
//   (config.php-তে একই নামের ক্লাস আগে থেকে থাকলে দ্বিগুণ ঘোষণার এরর এড়াতে গার্ড লাগানো আছে)
$bengaliCalendarFile = __DIR__ . '/bengali_calendar.php';
if (file_exists($bengaliCalendarFile) && !class_exists('BengaliFestivalCalendar') && !class_exists('BengaliCalendar')) {
    ob_start();
    require_once $bengaliCalendarFile;
    ob_end_clean(); // ফাইলটি সরাসরি খুললে যে টেস্ট-আউটপুট আসে, পেজে যেন না আসে
}

// ★ v4: বাংলা তারিখ — BengaliCalendar ক্লাস পেলেই topbar-এ ইংরেজি তারিখের পাশে বাংলা তারিখ দেখাবে
$bengaliDateStr = '';
if (class_exists('BengaliCalendar')) {
    try {
        $__bnDate = BengaliCalendar::toBengaliDate();
        $bengaliDateStr = $__bnDate['formatted'] ?? '';
    } catch (Throwable $ex) { $bengaliDateStr = ''; }
}

$FESTIVAL_LOGOS = [
    // ---- দুর্গাপুজো ২০২৬ (সংশোধিত পঞ্জিকা অনুযায়ী (★ v4.1): অষ্টমী ১৯ অক্টোবর, নবমী ২০, বিজয়া দশমী ২১ — এই তারিখগুলোর অগ্রাধিকার সবার উপরে) ----
    // 'durga' => true মানে: ব্যানার ফাইল না পাওয়া গেলে জেনেরিক দুর্গাপুজো ব্যানার ফল-ব্যাক চলবে
    '2026-10-16' => ['name' => 'শুভ দুর্গাপুজো',   'logo' => 'durgapujo.png',      'banner' => 'banners/durgapujo.png', 'durga' => true],
    '2026-10-17' => ['name' => 'শুভ মহাষষ্ঠী',     'logo' => 'maha_shashthi.png',  'banner' => 'banners/shashthi.png',  'durga' => true],
    '2026-10-18' => ['name' => 'শুভ মহাসপ্তমী',    'logo' => 'maha_saptami.png',   'banner' => 'banners/saptami.png',   'durga' => true],
    '2026-10-19' => ['name' => 'শুভ মহা অষ্টমী',   'logo' => 'maha_ashtami.png',   'banner' => 'banners/ashtami.png',   'durga' => true],
    '2026-10-20' => ['name' => 'শুভ মহানবমী',      'logo' => 'maha_navami.png',    'banner' => 'banners/navami.png',    'durga' => true],
    '2026-10-21' => ['name' => 'শুভ বিজয়া দশমী',  'logo' => 'bijoya_dashami.png', 'banner' => 'banners/dashami.png',   'durga' => true],
];

// ★ v4: bengali_calendar.php-এর সব উৎসব $FESTIVAL_LOGOS-এ অটো যোগ হয়
//   (দুর্গাপুজো, কালীপুজো, লক্ষ্মীপুজো, সরস্বতীপুজো, রথযাত্রা, পহেলা বৈশাখ, বড়দিন সহ ২৫+ উৎসব;
//    হাতে-লেখা এন্ট্রি থাকলে সেটারই অগ্রাধিকার — উৎসবের নাম/লোগো bengali_calendar.php থেকেই আসে)
if (class_exists('BengaliFestivalCalendar')) {
    try {
        $__calAll = BengaliFestivalCalendar::getAllFestivals();
        foreach ($__calAll as $__cf) {
            if (empty($__cf['logo']) || empty($__cf['start']) || empty($__cf['end'])) continue;
            $__cs = strtotime($__cf['start']);
            $__ce = strtotime($__cf['end']);
            if ($__cs === false || $__ce === false || $__ce < $__cs) continue;
            $__isDurga = preg_match('/(durga|maha_|bijoya_|mahalaya)/i', (string)$__cf['logo']) === 1;
            for ($__ct = $__cs; $__ct <= $__ce; $__ct += 86400) {
                $__ck = date('Y-m-d', $__ct);
                if (isset($FESTIVAL_LOGOS[$__ck])) continue; // হাতে-লেখা এন্ট্রির অগ্রাধিকার
                $FESTIVAL_LOGOS[$__ck] = [
                    'name'   => $__cf['name'],
                    'logo'   => $__cf['logo'],
                    'banner' => 'banners/' . $__cf['logo'],
                    'durga'  => $__isDurga,
                ];
            }
        }
        unset($__calAll, $__cf, $__cs, $__ce, $__ct, $__ck, $__isDurga);
    } catch (Throwable $ex) { /* ক্যালেন্ডার ফাইলে সমস্যা থাকলেও সাইট স্বাভাবিক চলবে */ }
}

// হেল্পার: ফাইলটি সার্ভারে সত্যিই আছে কিনা যাচাই করে পাথ দেয় (না থাকলে খালি স্ট্রিং)
// ★ v5 ফিক্স-১: একই ফাইলের একাধিক কপি (uploads/festivals ও festivals) থাকলে
//   "সবচেয়ে নতুন" (filemtime) কপিটি জেতে — আগে প্রথম ফোল্ডারের পুরোনো কপিই দেখাত,
//   ফলে নতুন আপলোড করা ছবি সাইটে বদলাচ্ছিল না।
// ★ v5 ফিক্স-২: ভিন্ন নামে আপলোড করা নতুন ভ্যারিয়েন্টও অটো-ডিটেক্ট হয়
//   (যেমন maha_ashtami_new.png / ashtami_fixed.png — নামে "ashtami" থাকলেই হবে;
//    জন্মাষ্টমী (janmashtami) ইচ্ছাকৃতভাবে বাদ — ভুল উৎসবের ছবি যেন না আসে)।
$festResolve = function ($rel) use ($FEST_BASE, $FEST_BANNER_BASE, $FEST_CANDIDATES) {
    $rel = trim((string)$rel);
    if ($rel === '') return '';
    if (filter_var($rel, FILTER_VALIDATE_URL)) return $rel;
    // প্রথমে অটো-ডিটেক্ট করা ফোল্ডারে খোঁজা হয়, তারপর বাকি সম্ভাব্য লোকেশনগুলোতে
    $__bases = array_values(array_unique(array_merge(
        [$FEST_BASE, $FEST_BANNER_BASE],
        $FEST_CANDIDATES
    )));
    $__winner = '';
    $__winnerM = -1;
    foreach ($__bases as $__b) {
        if ($__b === '') continue;
        $__cand = $__b . '/' . $rel;
        if (file_exists(__DIR__ . '/' . $__cand)) {
            $__m = @filemtime(__DIR__ . '/' . $__cand) ?: 0;
            if ($__m > $__winnerM) { $__winnerM = $__m; $__winner = $__cand; }
        }
    }
    if ($__winner === '') $__winner = file_exists(__DIR__ . '/' . $rel) ? $rel : '';
    if ($__winner === '') return '';
    // ★ v5: সব ক্যান্ডিডেট ফোল্ডারে নতুন ভ্যারিয়েন্ট খোঁজা হয় — সবচেয়ে নতুন ফাইলই জেতে
    $__stem = preg_replace('/\.(png|jpe?g|gif|webp)$/i', '', basename($rel));
    if ($__stem !== 'durgapujo') {
        $__kw = preg_replace('/^(maha_|durga_|bijoya_)/i', '', $__stem);
        $__sub = dirname($rel) === '.' ? '' : '/' . dirname($rel); // ব্যানার হলে banners/ সাব-ফোল্ডারেও খোঁজা হয়
        foreach ($__bases as $__b) {
            if ($__b === '' || !is_dir(__DIR__ . '/' . $__b . $__sub)) continue;
            foreach ((glob(__DIR__ . '/' . $__b . $__sub . '/*' . $__kw . '*') ?: []) as $__f) {
                $__bn = basename($__f);
                if (!is_file($__f)) continue;
                if (stripos($__bn, 'janmashtami') !== false) continue;
                if (!preg_match('/\.(png|jpe?g|gif|webp)$/i', $__bn)) continue;
                $__m = @filemtime($__f) ?: 0;
                if ($__m > $__winnerM) {
                    $__winnerM = $__m;
                    $__winner  = $__b . $__sub . '/' . $__bn;
                }
            }
        }
    }
    return $__winner;
};

// ★ v5: ব্রাউজার-ক্যাশ বাস্টার — একই নামে ছবি রিপ্লেস করলে শুধু রিফ্রেশেই নতুন ছবি
// দেখাবে (আগে ব্রাউজার পুরোনো ক্যাশ-করা ছবিই দেখাত, Ctrl+F5ও অনেক সময় লাগত)।
$festUrl = function ($p) {
    $p = trim((string)$p);
    if ($p === '' || filter_var($p, FILTER_VALIDATE_URL)) return $p;
    $m = @filemtime(__DIR__ . '/' . $p);
    return $m ? $p . '?v=' . $m : $p;
};

// অ্যাডমিন কাস্টম সেটিংস (ডাটাবেস)
$customVals = loadSettings($pdo, ['festival_logo_img', 'festival_logo_text']);
$custom_img = $customVals['festival_logo_img'] ?? '';
$custom_text = $customVals['festival_logo_text'] ?? '';

// আজকের তারিখ মিলিয়ে দেখুন (উপরে Asia/Kolkata টাইমজোন সেট করা আছে)
$activeEvent = $FESTIVAL_LOGOS[date('Y-m-d')] ?? null;

// ফল-ব্যাক: কনফিগ.php-তে পুরোনো BengaliFestivalCalendar ক্লাস থাকলে সেটিও কাজ করবে
if (!$activeEvent && class_exists('BengaliFestivalCalendar')) {
    $legacyEvent = BengaliFestivalCalendar::getActiveFestival();
    if (!empty($legacyEvent) && !empty($legacyEvent['logo'])) {
        $activeEvent = [
            'name'   => $legacyEvent['name'],
            'logo'   => $legacyEvent['logo'],
            'banner' => '',
        ];
    }
}

$festival_logo_img = '';
$festival_logo_text = '';
$festival_banner_img = '';
$hasFestivalLogo = false;

if ($activeEvent) {
    // দিনের লোগো না পাওয়া গেলে জেনেরিক দুর্গাপুজো লোগো — সেটিও না পেলে টেক্সট ফল-ব্যাক
    $festival_logo_img = $festResolve($activeEvent['logo']);
    if ($festival_logo_img === '') $festival_logo_img = $festResolve('durgapujo.png');
    $festival_logo_text = $activeEvent['name'];
    // দিনের ব্যানার না পাওয়া গেলে জেনেরিক ব্যানার ফল-ব্যাক
    $festival_banner_img = $festResolve($activeEvent['banner']);
    // ★ v4: শুধু দুর্গাপুজো-সেটের দিনে ব্যানার না পাওয়া গেলে জেনেরিক দুর্গাপুজো ব্যানার দেখাবে;
    //   অন্য উৎসবে (কালীপুজো, বড়দিন ইত্যাদি) ভুল দিনের ব্যানার দেখানো হবে না — ব্যানার বাদ থাকবে।
    if ($festival_banner_img === '' && !empty($activeEvent['durga'])) {
        $festival_banner_img = $festResolve('banners/durgapujo.png');
    }
    $hasFestivalLogo = true;
}

// অ্যাডমিন কাস্টম ইমেজ — ফাইল/URL বৈধ হলে তবেই অটোমেটিক লোগোর বদলে দেখাবে।
// ★ নতুন ফিক্স: ডাটাবেসে পুরোনো/ভাঙা পাথ সেট থাকলে সেটিকে উপেক্ষা করে
//   অটোমেটিক দুর্গাপুজো লোগোই দেখাবে (আগে এখানেই ছবি হারিয়ে যাচ্ছিল)।
if (!empty($custom_img)) {
    $resolvedCustomImg = $festResolve($custom_img);
    if ($resolvedCustomImg !== '') {
        $festival_logo_img = $resolvedCustomImg;
        $hasFestivalLogo = true;
    }
}
if (!empty($custom_text)) {
    $festival_logo_text = $custom_text;
    if (!$activeEvent) $hasFestivalLogo = true;
}
// ==============================================================
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

<!-- PERFORMANCE OPTIMIZATIONS: Preconnect & Preload -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preconnect" href="https://cdnjs.cloudflare.com">
<link rel="preconnect" href="https://query1.finance.yahoo.com">
<link rel="preconnect" href="https://translate.google.com">
<link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@300;400;500;600;700&family=Noto+Serif+Bengali:wght@400;600;700;900&display=swap" rel="stylesheet">
<link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"></noscript>

<style>
:root{--red:#B71C1C;--red-dark:#7F0000;--gold:#D4950A;--bg:#F4F1EB;--fg:#1A1A1A;--muted:#666;--card:#FFF;--border:#DDD5C8;--nav-bg:#1A1A1A;--sidebar-w:260px}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Hind Siliguri',sans-serif;background:var(--bg);color:var(--fg);line-height:1.6;position:relative;top:0}
body.top-margin { top: 0px !important; }
a{color:inherit;text-decoration:none}img{max-width:100%;display:block}
.kol-topbar{background:var(--red);color:#fff;padding:6px 0;font-size:12px;border-bottom:1px solid rgba(0,0,0,.2)}
.kol-topbar .container{display:grid;grid-template-columns:1fr auto 1fr;align-items:center;gap:8px;max-width:100%;padding:0 12px 0 0}
.kol-topbar .tl{display:flex;align-items:center;gap:10px;flex:1;min-width:0;margin-left:0;padding-left:0}
.kol-topbar .tr{display:flex;align-items:center;gap:8px;padding-right:12px;justify-content:flex-end}
.kol-topbar .clock{font-family:monospace;font-size:13px;color:var(--gold);font-weight:700;letter-spacing:.5px;min-width:90px;display:inline-flex;align-items:center;justify-content:center;margin-left:12px;padding:3px 10px;background:var(--red-dark);border:1px solid rgba(212,149,10,.3);border-radius:4px;height:24px}
.kol-topbar .date{color:rgba(255,255,255,.85);font-size:12px;border-left:1px solid rgba(255,255,255,.2);padding-left:12px}
.kol-topbar .date i{margin-right:4px;opacity:.7}
.kol-topbar .kol-epaper{justify-self:center;display:inline-flex;align-items:center;gap:6px;height:24px;padding:0 14px;border-radius:20px;background:rgba(0,0,0,.22);border:1px solid rgba(255,255,255,.28);color:#fff;font-size:12px;font-weight:600;text-decoration:none;transition:all .2s}
.kol-topbar .kol-epaper i{color:var(--gold);font-size:11px}
.kol-topbar .kol-epaper:hover{background:#fff;color:var(--red);border-color:#fff}
.kol-si{display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:50%;background:rgba(0,0,0,.2);color:#fff;font-size:10px;transition:all .2s}
.kol-si:hover{background:rgba(255,255,255,.3);transform:translateY(-1px)}
.kol-si.si-fb:hover{background:#1877F2}.kol-si.si-tw:hover{background:#000}.kol-si.si-yt:hover{background:#FF0000}.kol-si.si-ig:hover{background:linear-gradient(45deg,#f09433,#dc2743,#bc1888)}.kol-si.si-tg:hover{background:#0088cc}
.kol-login{font-family:'Hind Siliguri',sans-serif;background:var(--red);color:#fff;border:none;padding:6px 14px;border-radius:4px;cursor:pointer;font-size:12px;font-weight:600;transition:all .2s}
.kol-login:hover{background:var(--red-dark)}

.lang-switcher{position:relative;margin-right:8px}
.lang-btn{display:flex;align-items:center;gap:5px;background:rgba(255,255,255,.1);color:#fff;padding:4px 10px;border-radius:4px;font-size:11px;font-weight:600;cursor:pointer;border:none;transition:all .2s;font-family:inherit}
.lang-btn:hover{background:rgba(255,255,255,.2)}
.lang-btn i.fa-globe{font-size:11px}
.lang-btn i.fa-chevron-down{font-size:7px;transition:transform .2s}
.lang-btn.open i.fa-chevron-down{transform:rotate(180deg)}
.lang-dropdown{display:none;position:absolute;top:100%;right:0;margin-top:5px;background:#fff;border-radius:5px;box-shadow:0 5px 15px rgba(0,0,0,.2);overflow:hidden;z-index:1000;min-width:100px}
.lang-dropdown.open{display:block}
.lang-dropdown a{display:block;padding:8px 15px;font-size:12px;color:#333;text-decoration:none;transition:background .2s}
.lang-dropdown a:hover{background:#f0f0f0}

.breaking-bar{background:var(--red-dark);color:#fff;overflow:hidden;white-space:nowrap;height:38px;display:flex;align-items:center;position:relative}
.breaking-bar::before{content:'';position:absolute;left:0;top:0;bottom:0;width:130px;background:linear-gradient(to right,var(--red-dark) 0%,var(--red-dark) 60%,rgba(127,0,0,0.6) 80%,transparent 100%);z-index:3;pointer-events:none}
.breaking-bar::after{content:'';position:absolute;right:0;top:0;bottom:0;width:50px;background:linear-gradient(to left,var(--red-dark) 0%,transparent 100%);z-index:3;pointer-events:none}
.breaking-label{background:var(--gold);color:#000;padding:0 16px;font-weight:700;font-size:12px;height:100%;display:flex;align-items:center;justify-content:center;flex-shrink:0;letter-spacing:.5px;position:relative;z-index:5;box-shadow:6px 0 12px rgba(127,0,0,0.8);align-self:stretch}
.breaking-track{display:flex;align-items:center;height:100%;width:max-content;animation:bts 40s linear infinite;padding-left:30px;will-change:transform;position:relative;z-index:1}
.breaking-track span{padding:0 40px;font-size:13px;font-weight:500;white-space:nowrap;display:flex;align-items:center;text-align:center}
@keyframes bts{0%{transform:translateX(0)}100%{transform:translateX(-50%)}}
.breaking-bar:hover .breaking-track{animation-play-state:paused}

/* ====== HEADER WITH DYNAMIC PENDULUM LOGO ====== */
.kol-header{text-align:center;padding:30px 0 15px;border-bottom:3px solid var(--red);background:#fff;position:relative;overflow:visible}
.kol-header .container{position:relative;display:flex;align-items:center;justify-content:center;min-height:120px}
.header-content{position:relative;z-index:1;text-align:center;flex:1}
.header-content.has-logo{padding-left:200px /* Pushes text right to prevent overlap */}
.header-content.no-logo{padding-left:0}
.kol-header h1{font-family:'Noto Serif Bengali',serif;font-weight:900;font-size:clamp(28px,5vw,48px);color:var(--red);letter-spacing:3px;line-height:1.1}
.kol-header .tagline{font-size:12px;color:var(--muted);letter-spacing:4px;text-transform:uppercase;margin-top:2px}

.logo-pendulum{position:absolute;top:-15px;left:20px;display:flex;flex-direction:column;align-items:center;transform-origin:top center;animation:swing 3s ease-in-out infinite alternate;z-index:10}
.pendulum-string{width:2px;height:20px;background:#777;margin-bottom:-2px}
.logo-box{width:170px;height:120px;background:#fff;border:4px solid var(--red);box-shadow:0 5px 15px rgba(0,0,0,.2);overflow:hidden;border-radius:8px;display:flex;justify-content:center;align-items:center}
.festival-logo-img{width:100%;height:100%;object-fit:contain;background:#fff;padding:6px}
.festival-logo-text{font-family:'Noto Serif Bengali',serif;color:var(--red);font-weight:700;font-size:16px;text-align:center;padding:5px}
/* ====== FESTIVAL GREETING BANNER (transparent Bengali text art) ====== */
.festival-greeting{display:flex;justify-content:center;align-items:center;background:linear-gradient(180deg,#FFFDF7 0%,#F8F0DC 100%);border-bottom:2px solid var(--gold);position:relative;padding:14px 20px}
.festival-greeting img{max-width:min(560px,86vw);height:auto;filter:drop-shadow(0 2px 4px rgba(127,0,0,.15))}
.festival-greeting .fg-close{position:absolute;top:4px;right:10px;background:none;border:none;font-size:22px;color:var(--muted);cursor:pointer;line-height:1;padding:2px 6px}
.festival-greeting .fg-close:hover{color:var(--red)}

@keyframes swing {
    0% { transform: rotate(-12deg); }
    100% { transform: rotate(12deg); }
}
/* ================================================== */

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
.kn-pl-link .kpn{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:inherit;text-decoration:none}
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
.cpc-body{padding:25px 16px 12px}
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
.feat-side-title{background:var(--nav-bg);color:#fff;padding:10px 15px;font-family:'Noto Serif Bengali',serif;font-size:16px;font-weight:700;border-bottom:2px solid var(--red);display:flex;align-items:center;gap:8px}
.feat-side-title i{color:var(--gold)}
.feat-si{display:flex;gap:0;border-left:3px solid var(--red);overflow:hidden;transition:background .2s}
.feat-si+.feat-si{border-top:1px solid var(--border)}
.feat-si:first-child{border-radius:0}
.feat-si:last-child{border-radius:0 0 0 8px}
.feat-si:hover{background:#fdf6f6}
.feat-si img{width:110px;height:90px;object-fit:cover;flex-shrink:0}
.feat-si .pi{padding:12px 14px;display:flex;flex-direction:column;justify-content:center;min-width:0}
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
.vid-card .vc-info{padding:20px 14px 12px;display:flex;flex-direction:column;justify-content:center;min-width:0;flex:1}
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
.nc .nc-body{padding:28px 16px 14px}
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
    margin: 20px auto;
    display: block;
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
}
.single-headline { margin: 15px 0; padding: 10px 15px; background: #f8f9fa; border-left: 4px solid var(--red); font-size: 11px; font-weight: normal; color: #444; line-height: 1.5; }
.single-tags{margin-top:18px;padding-top:14px;border-top:1px solid var(--border);display:flex;flex-wrap:wrap;gap:6px;align-items:center}
.single-tags-label{font-size:12px;font-weight:700;color:var(--muted);margin-right:4px}

/* ====== NEWS SHARE BUTTONS ====== */
.news-share-container{display:flex;align-items:center;gap:10px;margin:15px 0;flex-wrap:wrap;background:#f8f9fa;padding:10px 15px;border-radius:8px;border:1px solid var(--border)}
.news-share-label{font-size:13px;font-weight:600;color:var(--muted);margin-right:5px}
.news-share-btn{display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:50%;color:#fff;font-size:14px;transition:transform .2s,opacity .2s;text-decoration:none;cursor:pointer;border:none}
.news-share-btn:hover{transform:translateY(-2px);opacity:.9}
.share-fb{background:#1877F2}.share-x{background:#000}.share-wa{background:#25D366}.share-li{background:#0A66C2}.share-copy{background:var(--muted)}
/* ================================ */

/* ====== VIDEO PAGE GRID ====== */
.video-page-sec{padding:12px 0 24px}
.video-page-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px}
.vp-card{background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 4px 10px rgba(0,0,0,0.05);transition:all .2s;border:1px solid var(--border)}
.vp-card:hover{transform:translateY(-4px);box-shadow:0 8px 20px rgba(0,0,0,0.1);border-color:rgba(183,28,28,0.3)}
.vp-card .video-embed{border-bottom:3px solid var(--red)}
.vp-card .vp-body{padding:26px 16px 16px}
.vp-card .vp-title{font-size:16px;font-weight:700;color:#1a1a1a;line-height:1.4;margin:0 0 8px 0;font-family:'Noto Serif Bengali',serif}
.vp-card .vp-date{font-size:12px;color:#888;margin:0}
.vp-card .vp-date i{margin-right:5px;color:var(--red)}
@media(max-width:1024px){.video-page-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:640px){.video-page-grid{grid-template-columns:1fr}}
/* ============================== */

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

.nse-live-badge {
    display: none;
    background: #ff1744;
    color: #fff;
    font-size: 9px;
    font-weight: 800;
    padding: 2px 6px;
    border-radius: 3px;
    margin-left: 6px;
    letter-spacing: 0.5px;
    vertical-align: middle;
    text-transform: uppercase;
    box-shadow: 0 0 6px rgba(255, 23, 68, 0.6);
    animation: nseLivePulse 1.5s ease-in-out infinite;
}
.nse-live-badge.show {
    display: inline-block;
}
.nse-live-badge::before {
    content: '';
    display: inline-block;
    width: 6px;
    height: 6px;
    background: #fff;
    border-radius: 50%;
    margin-right: 4px;
    vertical-align: middle;
    animation: nseDotBlink 1s ease-in-out infinite;
}
@keyframes nseLivePulse {
    0%, 100% { opacity: 1; box-shadow: 0 0 6px rgba(255, 23, 68, 0.6); }
    50% { opacity: 0.85; box-shadow: 0 0 12px rgba(255, 23, 68, 0.9); }
}
@keyframes nseDotBlink {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.3; }
}
.nse-closed-badge {
    display: inline-block;
    background: #555;
    color: #aaa;
    font-size: 9px;
    font-weight: 700;
    padding:2px 6px;
    border-radius: 3px;
    margin-left: 6px;
    letter-spacing: 0.5px;
    vertical-align: middle;
    text-transform: uppercase;
}
.nse-closed-badge.hide {
    display: none;
}

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
.twitter-embed-wrapper {
    position: relative;
    width: 100%;
    max-width: 550px;
    margin: 20px auto;
    background: #fff;
    border: 1px solid #e1e8ed;
    border-radius: 8px;
    overflow: hidden;
}
.twitter-embed-wrapper iframe {
    width: 100% !important;
    height: 500px !important;
    border: none;
    display: block;
    background: #fff;
}
/* (Old TTS button CSS removed — now using Bengali Female Anchor widget) */

/* ====== HIDE GOOGLE TRANSLATE TOP BAR & LOGO ====== */
body { top: 0 !important; }
.goog-te-banner-frame.skiptranslate { display: none !important; }
.goog-te-gadget { font-size: 0 !important; line-height: 0 !important; height: 0 !important; overflow: hidden !important; }
.goog-logo-link, .goog-te-balloon-frame { display: none !important; }
.skiptranslate.goog-te-gadget { height: 0 !important; white-space: nowrap; }
#goog-gt-tt { display: none !important; }
.goog-tooltip { display: none !important; }
.goog-tooltip:hover { display: none !important; }

/* ====== RESPONSIVE DESIGN FOR SMARTPHONS ====== */
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
@media(max-width:768px){
    .kol-header .container{flex-direction:column;justify-content:flex-start;padding-top:100px}
    .header-content.has-logo, .header-content.no-logo { padding-left: 0; }
    .logo-pendulum{position:relative;top:auto;left:auto;margin:0 auto 15px auto}
    .logo-box{width:140px;height:100px}
    .pendulum-string{height:15px}
}
@media(max-width:640px){
    .kol-topbar .container{display:flex;flex-direction:column;gap:5px;align-items:center}
    .kol-topbar .kol-epaper{order:-1}
    .kol-topbar .tl, .kol-topbar .tr{width:100%;justify-content:center;flex-wrap:wrap;padding:0}
    .kol-topbar .clock{margin-left:0}
    .kol-topbar .date{display:none}
    .kol-si.si-ig, .kol-si.si-tg { display: none; } 
    .kn-search input{width:70px}
    .kn-search input:focus{width:100px}
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
@media(max-width:480px){
    .kol-header{padding-top:15px}
    .kol-header .container{padding-top:85px}
    .logo-box{width:110px;height:80px;border-width:3px}
    .kol-header h1{font-size:26px;letter-spacing:1px}
    .kol-header .tagline{letter-spacing:2px}
    .cat-panel-row{grid-template-columns:1fr}
    .ad-bottom-sec .ad-block{min-width:100%}
    .sb-toggle{bottom:15px;right:15px;width:40px;height:40px;font-size:16px}
    .kol-nav .container{padding:0 5px}
    .kn-link{padding:12px 10px;font-size:12px}
    .sec-title{font-size:18px}
    .vid-info h3{font-size:16px}
    .vid-info{padding:12px 15px}
}

/* ===== App Download Icons (Google Play + App Store) ★ v6.3 — শুধু আইকন, কোনো ছবি/ফোল্ডার লাগে না ===== */
.ft-app-badges{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-top:14px}
.app-badge{display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:9px;background:#000;border:1px solid rgba(255,255,255,.22);color:#fff;font-size:19px;line-height:1;transition:transform .15s,box-shadow .15s,border-color .15s;text-decoration:none}
.app-badge:hover{transform:translateY(-2px);border-color:rgba(255,255,255,.5);box-shadow:0 6px 18px rgba(0,0,0,.45)}
.app-badge .fa-google-play{background:linear-gradient(135deg,#00d7fe,#00f076 45%,#ffd500 70%,#ff3a44);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}

/* ====== BENGALI FEMALE ANCHOR WIDGET (TTS News Reader) ====== */
.bn-anchor-fab{position:fixed;bottom:80px;right:20px;width:60px;height:60px;border-radius:50%;background:#fff;border:3px solid var(--red);box-shadow:0 6px 20px rgba(183,28,28,.4);cursor:pointer;z-index:9998;display:flex;align-items:center;justify-content:center;overflow:hidden;transition:all .25s ease;animation:bnAnchorPulse 2.5s infinite}
.bn-anchor-fab:hover{transform:scale(1.08);box-shadow:0 8px 28px rgba(183,28,28,.55)}
.bn-anchor-fab.hidden{transform:scale(0);opacity:0;pointer-events:none}
.bn-anchor-fab svg{width:100%;height:100%;display:block}
.bn-anchor-fab .fab-pulse-ring{position:absolute;inset:-3px;border-radius:50%;border:3px solid var(--red);opacity:0;animation:bnPulseRing 2.5s infinite}
/* "Now playing" live indicator on FAB when panel is hidden but audio is running */
.bn-anchor-fab.fab-playing::after{content:'';position:absolute;bottom:-1px;right:-1px;width:18px;height:18px;background:#00c853;border:3px solid #fff;border-radius:50%;z-index:5;animation:bnFabPlayingPulse 1.3s infinite}
@keyframes bnFabPlayingPulse{0%,100%{box-shadow:0 0 0 0 rgba(0,200,83,.75)}70%{box-shadow:0 0 0 10px rgba(0,200,83,0)}}
.bn-anchor-fab.fab-playing{animation:bnAnchorPulse 1.5s infinite}
.bn-anchor-fab.fab-playing .fab-pulse-ring{animation:bnPulseRing 1.3s infinite;border-color:#00c853}
@keyframes bnAnchorPulse{0%,100%{box-shadow:0 6px 20px rgba(183,28,28,.4)}50%{box-shadow:0 6px 26px rgba(183,28,28,.65)}}
@keyframes bnPulseRing{0%{transform:scale(1);opacity:.7}100%{transform:scale(1.45);opacity:0}}

.bn-anchor-panel{position:fixed;bottom:80px;right:20px;width:320px;max-width:calc(100vw - 30px);background:#fff;border-radius:14px;box-shadow:0 12px 40px rgba(0,0,0,.25);z-index:9999;overflow:hidden;border:1px solid var(--border);transform:translateY(20px) scale(.95);opacity:0;pointer-events:none;transition:all .3s cubic-bezier(.4,0,.2,1)}
.bn-anchor-panel.open{transform:translateY(0) scale(1);opacity:1;pointer-events:auto}
.bn-anchor-header{background:linear-gradient(135deg,var(--red) 0%,var(--red-dark) 100%);color:#fff;padding:10px 14px;display:flex;align-items:center;justify-content:space-between}
.bn-anchor-header .ah-title{font-family:'Noto Serif Bengali',serif;font-size:14px;font-weight:700;display:flex;align-items:center;gap:6px}
.bn-anchor-header .ah-title i{color:var(--gold);font-size:13px}
.bn-anchor-close{background:rgba(255,255,255,.15);border:none;color:#fff;width:26px;height:26px;border-radius:50%;cursor:pointer;font-size:14px;line-height:1;display:flex;align-items:center;justify-content:center;transition:background .2s}
.bn-anchor-close:hover{background:rgba(255,255,255,.35)}

.bn-anchor-body{display:flex;align-items:center;gap:12px;padding:14px;background:linear-gradient(180deg,#FFFDF7 0%,#F8F0DC 100%)}
.bn-anchor-avatar{width:110px;height:110px;flex-shrink:0;position:relative}
.bn-anchor-avatar svg{width:100%;height:100%;display:block}
.bn-anchor-info{flex:1;min-width:0}
.bn-anchor-name{font-family:'Noto Serif Bengali',serif;font-size:15px;font-weight:700;color:var(--fg);margin-bottom:2px}
.bn-anchor-status{font-size:11.5px;color:var(--muted);line-height:1.4;margin-bottom:8px;min-height:30px}
.bn-anchor-status .stat-dot{display:inline-block;width:8px;height:8px;border-radius:50%;background:#999;margin-right:5px;vertical-align:middle}
.bn-anchor-status.reading .stat-dot{background:#00c853;animation:bnDotBlink 1s infinite}
.bn-anchor-status.paused .stat-dot{background:#ff9800}
@keyframes bnDotBlink{0%,100%{opacity:1}50%{opacity:.3}}

.bn-anchor-controls{padding:8px 10px;background:var(--bg);border-top:1px solid var(--border);display:flex;gap:6px;flex-wrap:nowrap}
.bn-act-btn{flex:1;background:#fff;border:1px solid var(--border);color:var(--fg);padding:8px 6px;border-radius:6px;cursor:pointer;font-size:11.5px;font-weight:600;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:4px;transition:all .2s;white-space:nowrap}
.bn-act-btn:hover{background:var(--red);color:#fff;border-color:var(--red)}
.bn-act-btn.primary{background:var(--red);color:#fff;border-color:var(--red);flex:1.3}
.bn-act-btn.primary:hover{background:var(--red-dark);border-color:var(--red-dark)}
.bn-act-btn:disabled{opacity:.5;cursor:not-allowed}
.bn-act-btn i{font-size:10px}
.bn-act-btn#bnPanelHideBtn{background:var(--nav-bg);color:#fff;border-color:var(--nav-bg)}
.bn-act-btn#bnPanelHideBtn:hover{background:var(--red);border-color:var(--red)}

.bn-anchor-text-wrap{background:#fff;border-top:1px solid var(--border);overflow:hidden}
.bn-anchor-text-wrap.collapsed .bn-anchor-text-preview{display:none}
.bn-anchor-text-wrap.collapsed .bn-text-toggle .fa-eye{transform:scale(1)}
.bn-text-head{display:flex;align-items:center;justify-content:space-between;padding:7px 14px;background:var(--bg);border-bottom:1px solid var(--border)}
.bn-text-label{font-size:11.5px;font-weight:700;color:var(--fg);display:flex;align-items:center;gap:6px;font-family:'Noto Serif Bengali',serif}
.bn-text-label i{color:var(--red);font-size:11px}
.bn-text-toggle{background:#fff;border:1px solid var(--border);color:var(--muted);width:28px;height:28px;border-radius:5px;cursor:pointer;font-size:11px;display:flex;align-items:center;justify-content:center;transition:all .2s;font-family:inherit}
.bn-text-toggle:hover{background:var(--red);color:#fff;border-color:var(--red)}
.bn-anchor-text-preview{padding:12px 16px;font-size:15px;color:#1a1a1a;line-height:1.85;max-height:240px;overflow-y:auto;scrollbar-width:thin;font-family:'Hind Siliguri',sans-serif;letter-spacing:.2px}
.bn-anchor-text-preview::-webkit-scrollbar{width:6px}
.bn-anchor-text-preview::-webkit-scrollbar-track{background:var(--bg)}
.bn-anchor-text-preview::-webkit-scrollbar-thumb{background:#c9c2b6;border-radius:3px}
.bn-chunk{display:inline;padding:1px 2px;border-radius:2px;transition:background .15s,box-shadow .15s}
.bn-chunk.bn-chunk-active{background:#FFF3B0;box-shadow:0 0 0 1px #D4950A;border-radius:3px}
.bn-chunk.bn-chunk-done{color:#888}
.bn-anchor-text-wrap.collapsed{max-height:40px}
.bn-anchor-text-wrap.collapsed .bn-text-head{border-bottom:none}

/* Mouth animation while speaking */
.bn-anchor-avatar .bn-mouth-open{display:none;transform-origin:center}
.bn-anchor-avatar.speaking .bn-mouth-closed{display:none}
.bn-anchor-avatar.speaking .bn-mouth-open{display:block;animation:bnMouthTalk .32s ease-in-out infinite alternate}
@keyframes bnMouthTalk{0%{transform:scaleY(.35) scaleX(.92)}100%{transform:scaleY(1.25) scaleX(1.1)}}

/* Eye blink animation */
.bn-anchor-eye{transform-origin:center;animation:bnEyeBlink 4s infinite}
.bn-anchor-eye.right{animation-delay:0s}
.bn-anchor-eye.left{animation-delay:0s}
@keyframes bnEyeBlink{0%,92%,100%{transform:scaleY(1)}95%{transform:scaleY(.15)}}
.bn-anchor-avatar.speaking .bn-anchor-eye{animation-duration:2.5s}

/* Head subtle movement while speaking */
.bn-anchor-avatar.speaking .bn-head-group{animation:bnHeadBob 2.2s ease-in-out infinite;transform-origin:center bottom}
@keyframes bnHeadBob{0%,100%{transform:translateY(0) rotate(0)}50%{transform:translateY(-1px) rotate(.6deg)}}

@media(max-width:640px){
    .bn-anchor-fab{width:50px;height:50px;bottom:75px;right:14px}
    .bn-anchor-panel{width:calc(100vw - 28px);right:14px;bottom:135px}
}
/* ====== END BENGALI FEMALE ANCHOR WIDGET ====== */
</style>
</head>
<body>
<!-- Google Translate Hidden Element -->
<div id="google_translate_element" style="display:none;"></div>
<iframe id="google_esf" name="google_esf" src="https://googleads.g.doubleclick.net/pagead/html/r20260929/r20190131/zrt_lookup_fy2021.html" style="display: none;" loading="lazy"></iframe>

<?php
// ====== ই-পেপার (ePaper) লিংক ★ v6.4 ======
// আপনার ePaper পেজ রেডি হলে এখানে লিংক বসান (যেমন: 'epaper.php')।
// খালি স্ট্রিং ('') দিলে বাটনটি লুকিয়ে যাবে। **
$EPAPER_URL = 'epaper.php';
?>
<div class="kol-topbar">
<div class="container">
<div class="tl">
<span class="clock" id="liveClock">--:--:--</span>
<span class="date" id="liveDate"><i class="far fa-calendar-alt"></i> <?= htmlspecialchars($todayStr . (!empty($bengaliDateStr) ? ' । ' . $bengaliDateStr : '')) ?></span>
</div>
<?php if (trim($EPAPER_URL) !== ''): ?>
<a href="<?= htmlspecialchars($EPAPER_URL) ?>" class="kol-epaper" target="_blank" rel="noopener" aria-label="ই-পেপার"><i class="far fa-newspaper"></i> ই-পেপার</a>
<?php endif; ?>
<div class="tr">
    <!-- Language Switcher (Bengali Only) -->
    <div class="lang-switcher">
        <button class="lang-btn" id="langBtn"><i class="fas fa-globe"></i> <span id="currentLangText"><?= $current_lang ?></span></button>
        <div class="lang-dropdown" id="langDropdown" style="display:none;">
            <a href="#" data-lang="bn">বাংলা</a>
        </div>
    </div>
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
        <?php if ($hasFestivalLogo): ?>
        <!-- Pendulum Hanging Logo (Shows only if festival date matches today) -->
        <div class="logo-pendulum">
            <div class="pendulum-string"></div>
            <div class="logo-box">
                <?php 
                $fileExists = file_exists(__DIR__ . '/' . $festival_logo_img) || filter_var($festival_logo_img, FILTER_VALIDATE_URL);
                if (!empty($festival_logo_img) && $fileExists): ?>
                    <img src="<?= htmlspecialchars($festUrl($festival_logo_img)) ?>" alt="<?= htmlspecialchars($festival_logo_text) ?>" class="festival-logo-img" loading="eager" decoding="async">
                <?php else: ?>
                    <div class="festival-logo-text"><?= htmlspecialchars($festival_logo_text) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="header-content <?= $hasFestivalLogo ? 'has-logo' : 'no-logo'; ?>">
            <h1>সংবাদ সংকলন</h1>
            <div class="tagline">Truthy &amp; Trusted News Portal</div>
        </div>
    </div>
</header>

<?php if (!empty($festival_banner_img)): ?>
<!-- FESTIVAL GREETING BANNER (তারিখ অনুযায়ী অটোমেটিক আসে, × চেপে বন্ধ করা যায়) -->
<div class="festival-greeting" id="festivalGreeting">
    <button type="button" class="fg-close" onclick="document.getElementById('festivalGreeting').style.display='none'" aria-label="বন্ধ করুন">&times;</button>
    <img src="<?= htmlspecialchars($festUrl($festival_banner_img)) ?>" alt="<?= htmlspecialchars($festival_logo_text) ?>" decoding="async">
</div>
<?php endif; ?>

<?php if (!empty($bengaliDateStr)): ?>
<!-- ★ v4: বাংলা তারিখ — লাইভ ঘড়ির JS এটিকে ইংরেজি তারিখের পাশে জুড়ে দেয় -->
<script>window.__bengaliDateStr = <?= json_encode($bengaliDateStr, JSON_UNESCAPED_UNICODE) ?>;</script>
<?php endif; ?>

<?php if (!empty($adHeaderBanner)) echo $adHeaderBanner; ?>

<nav class="kol-nav" id="kolNav">
<div class="container">
<a href="?" class="kn-link kn-home"><i class="fas fa-home"></i> হোম</a>
<div class="kn-sep"></div>
<a href="?page=videos" class="kn-link<?= ($page === 'videos' || $page === 'video') ? ' active' : '' ?>"><i class="fas fa-video"></i> ভিডিও</a>
<div class="kn-sep"></div>
<a href="<?= htmlspecialchars($EPAPER_URL) ?>" class="kn-link" target="_blank" rel="noopener"><i class="far fa-newspaper"></i> ই-পেপার</a>
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
<?php foreach ($categories as $cat): $cn = $cat['name']; $cIcon = $navCatIcons[$cn] ?? 'fa-folder'; $cSubs = $catSubcategories[$cn] ?? []; $cHasSubs = !empty($cSubs); $cActive = ($currentCat === $cn && !$currentSub); $cPostCNT = $catPostCounts[$cn] ?? 0; if (in_array($cn, $navInlineCats)) continue; ?>
<div class="kn-pl-link<?= $cActive ? ' active' : '' ?><?= $cHasSubs ? ' has-psubs' : '' ?>" data-pcat="<?= htmlspecialchars($cn) ?>">
<div class="kpi"><i class="fas <?= $cIcon ?>"></i></div>
<a href="?cat=<?= urlencode($cn) ?>" class="kpn"><?= htmlspecialchars($cn) ?></a>
<?php if ($cPostCNT > 0): ?><span class="kpc"><?= $cPostCNT ?></span><?php endif; ?>
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
<button class="sb-close" id="sbClose"><i class="fas fa-chevron-left"></i> সাইডবার লুকাও</button>

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
<img src="<?= newsImage($pp['image'], $placeholderImgSm) ?>" alt="" loading="lazy" decoding="async">
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
<div class="video-page-sec">
    <h2 class="sec-title"><span style="background:var(--red);color:#fff;width:32px;height:32px;border-radius:7px;display:inline-flex;align-items:center;justify-content:center;font-size:14px;margin-right:8px"><i class="fas fa-video"></i></span> ভিডিও সংবাদ</h2>
    <?php if (!empty($videos)): ?>
        <div class="video-page-grid">
            <?php foreach ($videos as $vid):
                $videoCode = $vid['code'] ?? '';
                if (!empty($videoCode) && stripos($videoCode, '<iframe') === false && stripos($videoCode, '<video') === false && stripos($videoCode, '<embed') === false) {
                    $embedUrl = extractVideoEmbedUrl($videoCode);
                    if (!empty($embedUrl) && filter_var($embedUrl, FILTER_VALIDATE_URL)) {
                        $videoCode = '<iframe src="' . htmlspecialchars($embedUrl) . '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
                    }
                }
            ?>
            <div class="vp-card">
                <div class="video-embed"><?= $videoCode ?></div>
                <div class="vp-body">
                    <h3 class="vp-title"><?= htmlspecialchars($vid['title']) ?></h3>
                    <p class="vp-date"><i class="far fa-calendar-alt"></i> <?= date("d M Y", strtotime($vid['created_at'])) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="no-res">
            <i class="fas fa-video"></i>
            <h3>কোনো ভিডিও নেই</h3>
            <p>এই মুহূর্তে কোনো ভিডিও উপলব্ধ নেই।</p>
        </div>
    <?php endif; ?>
</div>
<?php elseif ($page === 'single' && $singleNews): 
    $shareUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '/');
    $shareTitle = htmlspecialchars($singleNews['title']);
    $shareTitleEnc = urlencode($shareTitle);
    $shareUrlEnc = urlencode($shareUrl);
?>
<article class="single-wrap">
<div style="margin-bottom:10px;">
<a href="?cat=<?= urlencode($singleNews['category']) ?>" class="cb"><?= htmlspecialchars($singleNews['category']) ?></a>
<?php if (!empty($singleNews['subcategory'])): ?>
<a href="?cat=<?= urlencode($singleNews['category']) ?>&sub=<?= (int)$singleNews['subcategory_id'] ?>" class="cb" style="background:var(--gold);color:#000;margin-left:5px;"><?= htmlspecialchars($singleNews['subcategory']) ?></a>
<?php endif; ?>
</div>

<!-- মূল টাইটেল এখানে দেখাবে (পরিবর্তন করা হবে না) -->
<h1 class="news-title"><?= $shareTitle ?></h1>

<!-- NEWS SHARE BUTTONS START -->
<div class="news-share-container">
    <span class="news-share-label">শেয়ার করুন:</span>
    <a href="https://www.facebook.com/sharer/sharer.php?u=<?= $shareUrlEnc ?>" target="_blank" rel="noopener noreferrer" class="news-share-btn share-fb" aria-label="Share on Facebook"><i class="fab fa-facebook-f"></i></a>
    <a href="https://twitter.com/intent/tweet?text=<?= $shareTitleEnc ?>&url=<?= $shareUrlEnc ?>" target="_blank" rel="noopener noreferrer" class="news-share-btn share-x" aria-label="Share on Twitter"><i class="fab fa-x-twitter"></i></a>
    <a href="https://api.whatsapp.com/send?text=<?= $shareTitleEnc ?>%20<?= $shareUrlEnc ?>" target="_blank" rel="noopener noreferrer" class="news-share-btn share-wa" aria-label="Share on WhatsApp"><i class="fab fa-whatsapp"></i></a>
    <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= $shareUrlEnc ?>" target="_blank" rel="noopener noreferrer" class="news-share-btn share-li" aria-label="Share on LinkedIn"><i class="fab fa-linkedin-in"></i></a>
    <button class="news-share-btn share-copy" id="copyLinkBtn" aria-label="Copy Link"><i class="fas fa-link"></i></button>
</div>
<!-- NEWS SHARE BUTTONS END -->

<div class="single-meta">
<span><i class="far fa-clock"></i> <?= formatDate($singleNews['created_at']) ?></span>
<span><i class="far fa-user"></i> সংবাদ সংকলন</span>
<?php if (!empty($singleNews['tags'])): ?>
<span><i class="fas fa-tags"></i> <?= htmlspecialchars($singleNews['tags']) ?></span>
<?php endif; ?>
</div>

<!-- হেডলাইন এখানে দেখাবে (ছবির আগে) -->
<?php if (!empty($singleNews['headline'])): ?>
    <div class="single-headline">
        <?= htmlspecialchars($singleNews['headline']) ?>
    </div>
<?php endif; ?>

<?php if (!empty($singleNews['image'])): ?>
<div class="single-img"><img src="<?= newsImage($singleNews['image'], $placeholderImg) ?>" alt="<?= htmlspecialchars($singleNews['title']) ?>" decoding="async" fetchpriority="high"></div>
<?php endif; ?>
<?php
 $content = $singleNews['content'];

 $content = preg_replace_callback(
    '~(?<!src=["\'])https?://(?:www\.)?(?:youtube\.com/(?:watch\?v=|embed/|v/)|youtu\.be/)([a-zA-Z0-9_-]{11})(?:\S*)~i',
    function($matches) {
        return '<div class="news-video-wrapper"><iframe src="https://www.youtube.com/embed/' . $matches[1] . '?rel=0&modestbranding=1" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div>';
    },
    $content
);

 $content = preg_replace_callback(
    '/<iframe[^>]+src=["\'](https?:\/\/(?:www\.)?(?:youtube(?:-nocookie)?\.com\/(?:embed\/|v\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})\S*)["\'][^>]*><\/iframe>/i',
    function($matches) {
        return '<div class="news-video-wrapper"><iframe src="https://www.youtube.com/embed/' . $matches[2] . '?rel=0&modestbranding=1" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div>';
    },
    $content
);

 $content = preg_replace_callback(
    '~(?<!src=["\'])https?://(?:www\.)?vimeo\.com/([0-9]+)(?:\S*)~i',
    function($matches) {
        return '<div class="news-video-wrapper"><iframe src="https://player.vimeo.com/video/' . $matches[1] . '" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe></div>';
    },
    $content
);

 $content = preg_replace_callback(
    '/<iframe[^>]+src=["\'](https?:\/\/(?:www\.)?player\.vimeo\.com\/video\/([0-9]+)\S*)["\'][^>]*><\/iframe>/i',
    function($matches) {
        return '<div class="news-video-wrapper"><iframe src="https://player.vimeo.com/video/' . $matches[2] . '" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe></div>';
    },
    $content
);

 $content = preg_replace_callback(
    '~(?<!src=["\'])https?://(?:www\.)?(?:twitter|x)\.com/(?:[^/]+/status|i/web/status)/([0-9]+)(?:\S*)~i',
    function($matches) {
        $tweet_id = $matches[1];
        $embed_url = 'https://platform.twitter.com/embed/Tweet.html?id=' . $tweet_id;
        return '<div class="twitter-embed-wrapper"><iframe src="' . $embed_url . '" frameborder="0" allow="autoplay; fullscreen" allowfullscreen></iframe></div>';
    },
    $content
);

 $content = preg_replace_callback(
    '/<iframe[^>]+src=["\'](https?:\/\/platform\.twitter\.com\/embed\/Tweet\.html\?[^"\']+)["\'][^>]*><\/iframe>/i',
    function($matches) {
        return '<div class="twitter-embed-wrapper"><iframe src="' . $matches[1] . '" frameborder="0" allow="autoplay; fullscreen" allowfullscreen></iframe></div>';
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
<div class="nc-img"><img src="<?= newsImage($ri['image'], $placeholderImg) ?>" alt="" loading="lazy" decoding="async"></div>
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
<img src="<?= newsImage($fm['image'], $placeholderImg) ?>" alt="" loading="lazy" decoding="async">
<div class="ov">
<span class="cb"><?= htmlspecialchars($fm['category']) ?><?php if (!empty($fm['subcategory'])): ?> / <?= htmlspecialchars($fm['subcategory']) ?><?php endif; ?></span>
<h2><?= htmlspecialchars($fm['title']) ?></h2>
<?php if (!empty($fm['excerpt'])): ?><p><?= htmlspecialchars($fm['excerpt']) ?></p><?php endif; ?>
</div>
</a>
<?php if (!empty($fsItems)): ?>
<div class="feat-side">
    <div class="feat-side-title"><i class="fas fa-star"></i> শীর্ষ সংবাদ</div>
    <?php foreach ($fsItems as $fsi): ?>
    <a href="?page=single&id=<?= $fsi['id'] ?>" class="feat-si">
    <img src="<?= newsImage($fsi['image'], $placeholderImgSm) ?>" alt="" loading="lazy" decoding="async">
    <div class="pi">
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
<img class="vid-poster" src="<?= htmlspecialchars($vidPoster) ?>" alt="<?= htmlspecialchars($vidMain['title'] ?? '') ?>" loading="lazy" decoding="async">
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
<img src="<?= htmlspecialchars($vThumb) ?>" alt="" loading="lazy" decoding="async">
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
    <div class="news-grid" id="newsGridContainer">
        <?php foreach ($pagedGrid as $ni): ?>
        <a href="?page=single&id=<?= $ni['id'] ?>" class="nc">
            <div class="nc-img"><img src="<?= newsImage($ni['image'], $placeholderImg) ?>" alt="" loading="lazy" decoding="async"></div>
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
                <div class="cpc-img"><img src="<?= newsImage($hpi['image'], $placeholderImg) ?>" alt="" loading="lazy" decoding="async"></div>
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
                <div style="width:100%;max-width:728px;height:90px;margin:0 auto;display:flex;align-items:center;justify-content:center;background:repeating-linear-gradient(45deg,#f3efe9,#f3efe9 12px,#ece7df 12px,#ece7df 24px);border:1px dashed #c9c2b6;border-radius:6px;color:#8a8378;font-size:14px;font-weight:600;letter-spacing:1px;">বিজ্ঞাপনের জায়গা</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="ad-block">
        <div class="ad-label">- Sponsored -</div>
        <div class="ad-content">
            <?php if (!empty($adVals['ad_bottom_2_embed'])): ?>
                <?= $adVals['ad_bottom_2_embed'] ?>
            <?php else: ?>
                <div style="width:100%;max-width:300px;height:250px;margin:0 auto;display:flex;align-items:center;justify-content:center;background:repeating-linear-gradient(45deg,#f3efe9,#f3efe9 12px,#ece7df 12px,#ece7df 24px);border:1px dashed #c9c2b6;border-radius:6px;color:#8a8378;font-size:14px;font-weight:600;letter-spacing:1px;">বিজ্ঞাপনের জায়গা</div>
            <?php endif; ?>
        </div>
    </div>
</div>

</div>

<?php if (!empty($nseTrackHTML) || empty($nseData)): ?>
<div class="nse-bar">
<div class="nse-lbl">
    <i class="fas fa-chart-line"></i> Share Market
    <span class="nse-live-badge" id="nseLiveBadge">LIVE</span>
    <span class="nse-closed-badge" id="nseClosedBadge">CLOSED</span>
</div>
<div class="nse-scroll">
<div class="nse-track" id="nseTrackContainer"><?= $nseTrackHTML ?></div>
</div>
</div>
<?php endif; ?>

<?php if (!empty($adFooterBanner)) echo $adFooterBanner; ?>

<?php
// ====== APP DOWNLOAD ICONS (Google Play + App Store) ★ v6.3 ======
// ★★ আপনার অ্যাপ পাবলিশ হলে শুধু নিচের ২টি লাইন বদলাবেন (অন্য কিছু ধরতে হবে না):
$PLAY_STORE_URL = '#';   // যেমন: 'https://play.google.com/store/apps/details?id=com.yoursite.app'
$APP_STORE_URL  = '#';   // যেমন: 'https://apps.apple.com/app/id123456789'
// আইকন দুটি সরাতে চাইলে false করুন:
$SHOW_APP_DOWNLOAD = true;
// --- নিচের অংশ অটোমেটিক; আইকন সাইটের Font Awesome ৬.৫ থেকে আসে, কোনো ছবি/ফোল্ডার লাগে না ---
$mkAppBadge = function ($href, $iconClass, $label) {
    $href = trim((string)$href);
    if (!$href) return '';
    return '<a class="app-badge" href="' . htmlspecialchars($href) . '" target="_blank" rel="noopener" aria-label="' . $label . '"><i class="' . $iconClass . '"></i></a>';
};
$playBadgeHTML  = $SHOW_APP_DOWNLOAD ? $mkAppBadge($PLAY_STORE_URL, 'fab fa-google-play', 'Google Play') : '';
$appleBadgeHTML = $SHOW_APP_DOWNLOAD ? $mkAppBadge($APP_STORE_URL,  'fab fa-apple',       'App Store') : '';
?>
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
<?php if ($playBadgeHTML !== '' || $appleBadgeHTML !== ''): ?>
<div class="ft-app-badges"><?= $playBadgeHTML ?><?= $appleBadgeHTML ?></div>
<?php endif; ?>
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
<li><a href="<?= htmlspecialchars($EPAPER_URL) ?>" target="_blank" rel="noopener"><i class="far fa-newspaper"></i> ই-পেপার</a></li>
<li><a href="?cat=জাতীয়">জাতীয়</a></li>
<li><a href="?cat=আন্তর্জাতিক">আন্তর্জাতিক</a></li>
<li><a href="?cat=খেলাধুলা">খেলাধুলা</a></li>
<li><a href="?cat=বিনোদন">বিনোদন</a></li>
<li><a href="?cat=প্রযুক্তি">প্রযুক্তি</a></li>
</ul>
</div>
<div class="ft-contact">
<h4><i class="fas fa-address-card"></i>&nbsp; যোগাযোগ</h4>
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

<button class="sb-toggle" id="sbToggle" aria-label="Sidebar Toggle"><i class="fas fa-bars"></i></button>

<?php
// ★ v2: শুধুমাত্র single news page-এ TTS প্যানেল দেখাবে (হোমপেজে নয়)।
// ইউজার যখন কোনো খবরে ক্লিক করে পড়তে যাবে, তখনই এই প্যানেল দেখাবে।
if ($page === 'single' && !empty($singleNews)): ?>
<!-- ====== BENGALI FEMALE ANCHOR WIDGET (TTS News Reader) ====== -->
<!-- Floating Avatar Button -->
<button id="bnAnchorFab" class="bn-anchor-fab" aria-label="সংবাদ শুনুন" title="সংবাদ শুনুন (বাংলা অ্যাঙ্কর)">
    <span class="fab-pulse-ring"></span>
    <svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <defs>
            <radialGradient id="bnGradFace" cx="50%" cy="40%" r="60%">
                <stop offset="0%" stop-color="#FBD9B6"/>
                <stop offset="100%" stop-color="#E8B89A"/>
            </radialGradient>
            <linearGradient id="bnGradHair" x1="0%" y1="0%" x2="0%" y2="100%">
                <stop offset="0%" stop-color="#3a1f10"/>
                <stop offset="100%" stop-color="#1a0e08"/>
            </linearGradient>
            <linearGradient id="bnGradBlouse" x1="0%" y1="0%" x2="0%" y2="100%">
                <stop offset="0%" stop-color="#D32F2F"/>
                <stop offset="100%" stop-color="#7F0000"/>
            </linearGradient>
        </defs>
        <!-- Bg circle -->
        <circle cx="100" cy="100" r="98" fill="#FFFDF7" stroke="#B71C1C" stroke-width="3"/>
        <!-- Body (saree blouse) -->
        <path d="M 30 200 Q 30 160 80 145 Q 100 142 120 145 Q 170 160 170 200 Z" fill="url(#bnGradBlouse)"/>
        <!-- Neck -->
        <rect x="88" y="120" width="24" height="22" fill="#E8B89A"/>
        <!-- Necklace -->
        <path d="M 85 138 Q 100 145 115 138" stroke="#D4950A" stroke-width="1.5" fill="none"/>
        <circle cx="100" cy="146" r="2.5" fill="#D4950A"/>
        <!-- Hair back -->
        <path d="M 55 95 Q 50 50 100 42 Q 150 50 145 95 L 145 130 Q 130 110 100 110 Q 70 110 55 130 Z" fill="url(#bnGradHair)"/>
        <g class="bn-head-group">
            <!-- Face -->
            <ellipse cx="100" cy="95" rx="36" ry="42" fill="url(#bnGradFace)"/>
            <!-- Hair front (side-swept bangs) -->
            <path d="M 62 80 Q 70 55 100 53 Q 130 55 138 80 Q 130 70 115 72 Q 95 75 80 88 Q 70 80 62 80 Z" fill="url(#bnGradHair)"/>
            <!-- Eyebrows -->
            <path d="M 76 86 Q 84 83 92 86" stroke="#2a1810" stroke-width="1.8" fill="none" stroke-linecap="round"/>
            <path d="M 108 86 Q 116 83 124 86" stroke="#2a1810" stroke-width="1.8" fill="none" stroke-linecap="round"/>
            <!-- Eyes -->
            <ellipse class="bn-anchor-eye left" cx="84" cy="96" rx="3.5" ry="4.5" fill="#1a1a1a"/>
            <ellipse class="bn-anchor-eye right" cx="116" cy="96" rx="3.5" ry="4.5" fill="#1a1a1a"/>
            <!-- Eye highlights -->
            <circle cx="85" cy="94" r="1" fill="#fff"/>
            <circle cx="117" cy="94" r="1" fill="#fff"/>
            <!-- Nose -->
            <path d="M 100 100 L 97 112 Q 100 114 103 112 Z" fill="#D4A582" opacity="0.6"/>
            <!-- Lips (closed mouth - default state) -->
            <g class="bn-mouth-closed">
                <path d="M 90 122 Q 100 126 110 122 Q 100 130 90 122 Z" fill="#B03A2E"/>
                <path d="M 90 122 Q 100 124 110 122" stroke="#7B1F1A" stroke-width="0.5" fill="none"/>
            </g>
            <!-- Lips (open mouth - speaking state) -->
            <g class="bn-mouth-open">
                <ellipse cx="100" cy="125" rx="9" ry="5" fill="#7B1F1A"/>
                <ellipse cx="100" cy="124" rx="6" ry="3" fill="#C0392B"/>
                <path d="M 91 122 Q 100 126 109 122" stroke="#5a1a15" stroke-width="1" fill="none"/>
            </g>
            <!-- Earrings -->
            <circle cx="65" cy="108" r="2.5" fill="#D4950A"/>
            <circle cx="65" cy="113" r="1.5" fill="#D4950A"/>
            <circle cx="135" cy="108" r="2.5" fill="#D4950A"/>
            <circle cx="135" cy="113" r="1.5" fill="#D4950A"/>
            <!-- Bindi (red dot on forehead) -->
            <circle cx="100" cy="74" r="2.5" fill="#B71C1C"/>
        </g>
    </svg>
</button>

<!-- Anchor Panel (expanded) -->
<div id="bnAnchorPanel" class="bn-anchor-panel" role="dialog" aria-label="বাংলা সংবাদ অ্যাঙ্কর">
    <div class="bn-anchor-header">
        <div class="ah-title"><i class="fas fa-microphone"></i> বাংলা সংবাদ অ্যাঙ্কর</div>
        <button type="button" class="bn-anchor-close" id="bnAnchorClose" aria-label="বন্ধ করুন">&times;</button>
    </div>
    <div class="bn-anchor-body">
        <div class="bn-anchor-avatar" id="bnAnchorAvatar">
            <svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <defs>
                    <radialGradient id="bnGradFace2" cx="50%" cy="40%" r="60%">
                        <stop offset="0%" stop-color="#FBD9B6"/>
                        <stop offset="100%" stop-color="#E8B89A"/>
                    </radialGradient>
                    <linearGradient id="bnGradHair2" x1="0%" y1="0%" x2="0%" y2="100%">
                        <stop offset="0%" stop-color="#3a1f10"/>
                        <stop offset="100%" stop-color="#1a0e08"/>
                    </linearGradient>
                    <linearGradient id="bnGradBlouse2" x1="0%" y1="0%" x2="0%" y2="100%">
                        <stop offset="0%" stop-color="#D32F2F"/>
                        <stop offset="100%" stop-color="#7F0000"/>
                    </linearGradient>
                </defs>
                <circle cx="100" cy="100" r="98" fill="#FFFDF7" stroke="#B71C1C" stroke-width="3"/>
                <path d="M 30 200 Q 30 160 80 145 Q 100 142 120 145 Q 170 160 170 200 Z" fill="url(#bnGradBlouse2)"/>
                <rect x="88" y="120" width="24" height="22" fill="#E8B89A"/>
                <path d="M 85 138 Q 100 145 115 138" stroke="#D4950A" stroke-width="1.5" fill="none"/>
                <circle cx="100" cy="146" r="2.5" fill="#D4950A"/>
                <path d="M 55 95 Q 50 50 100 42 Q 150 50 145 95 L 145 130 Q 130 110 100 110 Q 70 110 55 130 Z" fill="url(#bnGradHair2)"/>
                <g class="bn-head-group">
                    <ellipse cx="100" cy="95" rx="36" ry="42" fill="url(#bnGradFace2)"/>
                    <path d="M 62 80 Q 70 55 100 53 Q 130 55 138 80 Q 130 70 115 72 Q 95 75 80 88 Q 70 80 62 80 Z" fill="url(#bnGradHair2)"/>
                    <path d="M 76 86 Q 84 83 92 86" stroke="#2a1810" stroke-width="1.8" fill="none" stroke-linecap="round"/>
                    <path d="M 108 86 Q 116 83 124 86" stroke="#2a1810" stroke-width="1.8" fill="none" stroke-linecap="round"/>
                    <ellipse class="bn-anchor-eye left" cx="84" cy="96" rx="3.5" ry="4.5" fill="#1a1a1a"/>
                    <ellipse class="bn-anchor-eye right" cx="116" cy="96" rx="3.5" ry="4.5" fill="#1a1a1a"/>
                    <circle cx="85" cy="94" r="1" fill="#fff"/>
                    <circle cx="117" cy="94" r="1" fill="#fff"/>
                    <path d="M 100 100 L 97 112 Q 100 114 103 112 Z" fill="#D4A582" opacity="0.6"/>
                    <g class="bn-mouth-closed">
                        <path d="M 90 122 Q 100 126 110 122 Q 100 130 90 122 Z" fill="#B03A2E"/>
                        <path d="M 90 122 Q 100 124 110 122" stroke="#7B1F1A" stroke-width="0.5" fill="none"/>
                    </g>
                    <g class="bn-mouth-open">
                        <ellipse cx="100" cy="125" rx="9" ry="5" fill="#7B1F1A"/>
                        <ellipse cx="100" cy="124" rx="6" ry="3" fill="#C0392B"/>
                        <path d="M 91 122 Q 100 126 109 122" stroke="#5a1a15" stroke-width="1" fill="none"/>
                    </g>
                    <circle cx="65" cy="108" r="2.5" fill="#D4950A"/>
                    <circle cx="65" cy="113" r="1.5" fill="#D4950A"/>
                    <circle cx="135" cy="108" r="2.5" fill="#D4950A"/>
                    <circle cx="135" cy="113" r="1.5" fill="#D4950A"/>
                    <circle cx="100" cy="74" r="2.5" fill="#B71C1C"/>
                </g>
            </svg>
        </div>
        <div class="bn-anchor-info">
            <div class="bn-anchor-status" id="bnAnchorStatus"><span class="stat-dot"></span><span id="bnStatusText">প্রস্তুত। সংবাদ শুনতে ক্লিক করুন।</span></div>
            <div id="bnVoiceBadge" style="display:flex;gap:6px;align-items:center;font-size:10px;color:var(--muted)"><i class="fas fa-volume-up" style="color:var(--gold)"></i> <span>বাংলা নারী কণ্ঠ</span></div>
        </div>
    </div>
    <div class="bn-anchor-controls">
        <button type="button" class="bn-act-btn primary" id="bnReadBtn"><i class="fas fa-play"></i> শুনুন</button>
        <button type="button" class="bn-act-btn" id="bnStopBtn" disabled><i class="fas fa-stop"></i> থামান</button>
        <button type="button" class="bn-act-btn" id="bnHideBtn" title="টেক্সট দেখান/লুকান"><i class="fas fa-eye-slash"></i> লুকান</button>
        <button type="button" class="bn-act-btn" id="bnPanelHideBtn" title="প্যানেল হাইড করুন"><i class="fas fa-chevron-down"></i> হাইড</button>
    </div>
    <div class="bn-anchor-text-wrap" id="bnTextWrap" style="display:none;">
        <div class="bn-text-head">
            <span class="bn-text-label"><i class="fas fa-align-left"></i> পড়া হচ্ছে</span>
            <button type="button" class="bn-text-toggle" id="bnTextToggle" aria-label="টেক্সট দেখান/লুকান" title="টেক্সট দেখান/লুকান"><i class="fas fa-eye"></i></button>
        </div>
        <div class="bn-anchor-text-preview" id="bnTextPreview"></div>
    </div>
</div>
<!-- ====== END BENGALI FEMALE ANCHOR WIDGET ====== -->
<?php endif; // end single-news-only widget ?>

<!-- Permission Popup -->
<div id="allowNewsPopup" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:100000;align-items:center;justify-content:center;">
    <div style="background:#fff;padding:40px 30px;border-radius:12px;text-align:center;max-width:380px;width:90%;box-shadow:0 10px 30px rgba(0,0,0,.3);">
        <div style="width:70px;height:70px;background:#FFF0F0;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">
            <i class="fas fa-bell" style="font-size:32px;color:var(--red);"></i>
        </div>
        <h3 style="font-family:'Noto Serif Bengali',serif;margin-bottom:15px;color:#333;font-size:22px;font-weight:700;">নোটিফিকেশন চালু করবেন?</h3>
        <p style="font-size:16px;color:#333;margin-bottom:25px;font-weight:600;">নতুন খবর প্রকাশিত হলে সিস্টেমে (Windows-এ) নোটিফিকেশন পাবেন।</p>
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
            <h4 style="font-family:'Noto Serif Bengali',serif;font-size:14px;font-weight:700;margin:0 0 4px 0;color:#333;">নতুন খবর প্রকাশিত হয়েছে!</h4>
            <p id="newNewsTitleFallback" style="font-size:13px;color:#666;margin:0;line-height:1.4;"></p>
        </div>
        <button id="btnCloseNewNews" style="background:none;border:none;color:#999;cursor:pointer;font-size:16px;padding:5px;">&times;</button>
    </div>
    <a id="newNewsLinkFallback" href="#" style="display:block;text-align:center;padding:10px;background:var(--red);color:#fff;text-decoration:none;font-weight:bold;font-size:13px;font-family:'Hind Siliguri',sans-serif;">Read Now</a>
</div>

<!-- ★ v6.8: নোটিফিকেশন বেল বাটন — সিস্টেম নোটিফিকেশন চালু না থাকলে বাম-নিচে কর্নারে ভাসবে;
     ক্লিক করলে পারমিশন পপ-আপ খুলবে (JS শর্ত অনুযায়ী দেখানো/লুকানো হয়) -->
<style>
@keyframes nnBellPulse { 0% { box-shadow: 0 0 0 0 rgba(183,28,28,.45); } 70% { box-shadow: 0 0 0 14px rgba(183,28,28,0); } 100% { box-shadow: 0 0 0 0 rgba(183,28,28,0); } }
#notifyBellBtn { animation: nnBellPulse 2s infinite; transition: transform .2s ease; }
#notifyBellBtn:hover { transform: scale(1.08); }
</style>
<div id="notifyBellBtn" style="display:none;position:fixed;bottom:20px;left:20px;width:48px;height:48px;background:var(--red);color:#fff;border-radius:50%;z-index:99999;align-items:center;justify-content:center;cursor:pointer;box-shadow:0 4px 15px rgba(0,0,0,.3);">
    <i class="fas fa-bell" style="font-size:20px;"></i>
</div>

<?php if (!empty($adPopup)): ?>
<!-- FIX: Changed from display:none to opacity:0 to prevent AdSense/script blocking -->
<div id="popupAdOverlay" style="opacity:0;pointer-events:none;visibility:hidden;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:100001;align-items:center;justify-content:center;transition:opacity .3s ease;">
    <div style="position:relative;max-width:500px;width:90%;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 10px 40px rgba(0,0,0,.3);">
        <button onclick="closePopupAd()" style="position:absolute;top:8px;right:10px;z-index:10;background:rgba(0,0,0,.5);color:#fff;border:none;width:28px;height:28px;border-radius:50%;cursor:pointer;font-size:16px;">&times;</button>
        <?= $adPopup ?>
    </div>
</div>
<script>
function closePopupAd() {
    var o = document.getElementById('popupAdOverlay');
    if (o) {
        o.style.opacity = '0';
        o.style.pointerEvents = 'none';
        o.style.visibility = 'hidden';
    }
}
setTimeout(function(){
    var o=document.getElementById('popupAdOverlay');
    if(o){
        o.style.opacity='1';
        o.style.pointerEvents='auto';
        o.style.visibility='visible';
        o.style.display='flex';
    }
},5000);
document.addEventListener('click',function(e){
    if(e.target.id==='popupAdOverlay') closePopupAd();
});
</script>
<?php endif; ?>

<!-- PERFORMANCE FIX: Google Translate Script now loads AFTER page is fully loaded to prevent slow page rendering -->
<script type="text/javascript">
function googleTranslateElementInit() {
    new google.translate.TranslateElement({
        pageLanguage: 'bn',
        includedLanguages: 'bn',
        layout: google.translate.TranslateElement.InlineLayout.SIMPLE,
        autoDisplay: false
    }, 'google_translate_element');
}
window.addEventListener('load', function() {
    var script = document.createElement('script');
    script.type = 'text/javascript';
    script.src = '//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit';
    document.head.appendChild(script);
});
</script>

<!-- ================================================== -->
<!-- LIVE CLOCK & DATE UPDATE SCRIPT                  -->
<!-- JS Clock added to completely remove white bg      -->
<!-- ================================================== -->
<script>
(function () {
  function pad(n) {
    n = String(n);
    return n.length < 2 ? '0' + n : n;
  }

  var dayNames = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
  var monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];

  var IST_OFFSET_MS = 5.5 * 60 * 60 * 1000;
  var serverOffset = 0;

  function fetchApiTime() {
    try {
      fetch('https://timeapi.world/api/time/Asia/Kolkata')
        .then(function(response) {
          if (!response.ok) throw new Error('API error');
          return response.json();
        })
        .then(function(data) {
          var apiTimestamp = null;
          if (data.unixtime) {
            apiTimestamp = parseInt(data.unixtime, 10) * 1000;
          } else if (data.datetime) {
            var d = new Date(data.datetime);
            if (!isNaN(d.getTime())) apiTimestamp = d.getTime();
          } else if (data.year && data.month && data.day) {
            var h = data.hour || data.hours || 0;
            var m = data.minute || data.minutes || 0;
            var s = data.second || data.seconds || 0;
            var d2 = new Date(Date.UTC(data.year, data.month - 1, data.day, h, m, s));
            if (!isNaN(d2.getTime())) apiTimestamp = d2.getTime();
          }
          if (apiTimestamp !== null && !isNaN(apiTimestamp)) {
            serverOffset = apiTimestamp - Date.now();
          }
        })
        .catch(function(e) {
          console.warn('Time API fetch failed, using browser time with IST offset');
        });
    } catch (e) {}
  }

  function updateClock() {
    try {
      var istTimestamp = Date.now() + serverOffset;
      istTimestamp = istTimestamp + IST_OFFSET_MS;
      var istDate = new Date(istTimestamp);

      var h = istDate.getUTCHours();
      var m = istDate.getUTCMinutes();
      var s = istDate.getUTCSeconds();
      
      // Convert to 12-hour format
      var ampm = h >= 12 ? 'PM' : 'AM';
      h = h % 12;
      h = h ? h : 12; // the hour '0' should be '12'
      
      var timeStr = pad(h) + ':' + pad(m) + ':' + pad(s) + ' ' + ampm;
      var clockEl = document.getElementById('liveClock');
      if (clockEl) {
        clockEl.textContent = timeStr;
      }
    } catch (e) {}
  }

  function updateDate() {
    try {
      var istTimestamp = Date.now() + serverOffset;
      istTimestamp = istTimestamp + IST_OFFSET_MS;
      var istDate = new Date(istTimestamp);

      var dateEl = document.getElementById('liveDate');
      if (dateEl) {
        var day = dayNames[istDate.getUTCDay()];
        var month = monthNames[istDate.getUTCMonth()];
        var dateStr = pad(istDate.getUTCDate()) + ' ' + month + ' ' + istDate.getUTCFullYear() + ', ' + day;
        if (window.__bengaliDateStr) dateStr += ' । ' + window.__bengaliDateStr;
        dateEl.innerHTML = '<i class="far fa-calendar-alt"></i> ' + dateStr;
      }
    } catch (e) {}
  }

  fetchApiTime();
  updateClock();
  updateDate();
  setInterval(function() {
    updateClock();
    updateDate();
  }, 1000);
  setInterval(fetchApiTime, 5 * 60 * 1000);
})();
</script>

<!-- ================================================== -->
<!-- NSE MARKET HOURS CHECK - Auto Live/Closed Badge   -->
<!-- UPDATED: Included Indian Stock Market Holidays    -->
<!-- ================================================== -->
<script>
(function () {
  function checkMarketStatus() {
    try {
      var now = new Date();
      var istTimestamp = now.getTime() + (5.5 * 60 * 60 * 1000);
      var istDate = new Date(istTimestamp);

      var day = istDate.getUTCDay();
      var hours = istDate.getUTCHours();
      var minutes = istDate.getUTCMinutes();
      var timeInMinutes = hours * 60 + minutes;

      var year = istDate.getUTCFullYear();
      var month = istDate.getUTCMonth() + 1; // 1-12
      var date = istDate.getUTCDate();

      // Format current date to YYYY-MM-DD
      var dateStr = year + '-' + (month < 10 ? '0' + month : month) + '-' + (date < 10 ? '0' + date : date);

      // List of NSE/BSE Trading Holidays (2024 to 2028)
      var marketHolidays = [
        // 2024 Holidays
        "2024-01-26", "2024-03-25", "2024-03-29", "2024-04-11", "2024-04-17",
        "2024-04-23", "2024-05-01", "2024-05-23", "2024-06-17", "2024-07-17",
        "2024-08-15", "2024-10-02", "2024-11-01", "2024-11-15", "2024-12-25",
        
        // 2025 Holidays
        "2025-02-26", "2025-03-14", "2025-03-31", "2025-04-10", "2025-04-14",
        "2025-04-18", "2025-05-01", "2025-08-15", "2025-08-16", "2025-10-02",
        "2025-10-21", "2025-11-05", "2025-12-25",
        
        // 2026 Holidays
        "2026-01-26", "2026-02-16", "2026-03-04", "2026-03-26", "2026-04-02",
        "2026-04-10", "2026-05-01", "2026-08-15", "2026-10-02", "2026-11-10",
        "2026-12-25",
        
        // 2027 Holidays
        "2027-01-26", "2027-03-24", "2027-03-26", "2027-05-01", "2027-08-15",
        "2027-10-02", "2027-10-30", "2027-12-25",
        
        // 2028 Holidays
        "2028-01-26", "2028-03-13", "2028-03-31", "2028-05-01", "2028-08-15",
        "2028-10-02", "2028-10-18", "2028-12-25"
      ];

      // Check if today is a holiday
      var isHoliday = marketHolidays.indexOf(dateStr) !== -1;

      var isWeekday = day >= 1 && day <= 5;
      
      // Market hours: 9:15 AM to 3:30 PM
      // 9 * 60 + 15 = 555
      // 15 * 60 + 30 = 930
      var isMarketHours = timeInMinutes >= 555 && timeInMinutes < 930; 
      
      var isMarketOpen = isWeekday && isMarketHours && !isHoliday;

      var liveBadge = document.getElementById('nseLiveBadge');
      var closedBadge = document.getElementById('nseClosedBadge');

      if (liveBadge && closedBadge) {
        if (isMarketOpen) {
          liveBadge.classList.add('show');
          closedBadge.classList.add('hide');
        } else {
          liveBadge.classList.remove('show');
          closedBadge.classList.remove('hide');
        }
      }
    } catch (e) {
      console.warn('Market status check error:', e);
    }
  }

  checkMarketStatus();
  setInterval(checkMarketStatus, 60000);
})();
</script>

<!-- ================================================== -->
<!-- MAIN APPLICATION SCRIPT                           -->
<!-- ================================================== -->
<script>
function escapeHtml(text) {
    if(!text) return '';
    var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
}

(function(){
var sbToggle=document.getElementById('sbToggle'),kolSidebar=document.getElementById('kolSidebar'),kolLayout=document.getElementById('kolLayout'),sbClose=document.getElementById('sbClose');

function toggleSidebar(){
    kolLayout.classList.toggle('sb-hidden');
    var icon=sbToggle.querySelector('i');
    if(kolLayout.classList.contains('sb-hidden')){icon.className='fas fa-bars';sbToggle.title='Show Sidebar';}
    else{icon.className='fas fa-times';sbToggle.title='Hide Sidebar';}
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

// FIX: If clicking the text link, navigate normally instead of just toggling
document.querySelectorAll('.kn-pl-link.has-psubs').forEach(function(row){row.addEventListener('click',function(e){if(e.target.closest('.kn-sub-link')||e.target.closest('.kpn'))return;var cat=this.getAttribute('data-pcat');var sl=document.querySelector('.kn-subs[data-psubs="'+cat+'"]');var tb=this.querySelector('.kn-sub-tog');if(sl)sl.classList.toggle('open');if(tb)tb.classList.toggle('open');});});
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

// Language Switcher Logic (Bengali Only - effectively disabled dropdown)
var langBtn = document.getElementById('langBtn');
if(langBtn) {
    langBtn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
    });
}

// (Old single-button TTS removed — now using Bengali Female Anchor widget)

// ====== COPY LINK FUNCTIONALITY FOR SHARE BUTTONS ======
var copyBtn = document.getElementById('copyLinkBtn');
if(copyBtn) {
    copyBtn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var tempInput = document.createElement("input");
        tempInput.value = window.location.href;
        document.body.appendChild(tempInput);
        tempInput.select();
        tempInput.setSelectionRange(0, 99999); // For mobile devices
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
// ======================================================

// ====== BACKGROUND AJAX FETCH FOR NSE STOCKS (SPEEDS UP PAGE LOAD) ======
setTimeout(function(){
    fetch('?ajax_nse=1').then(function(r){return r.json();}).then(function(res){
        if(res.status === 'fresh' && res.data) {
            var track = document.getElementById('nseTrackContainer');
            if(track) {
                var items = [];
                res.data.forEach(function(ni){
                    if(!ni.fetched) return;
                    var cls = ni.change >= 0 ? 'nse-up' : 'nse-down';
                    var sign = ni.change >= 0 ? '+' : '';
                    items.push('<div class="nse-si"><span class="nse-sym">' + escapeHtml(ni.short) + '</span><span class="nse-pr">' + number_format(ni.price, 2) + '</span><span class="nse-chg ' + cls + '">' + sign + number_format(ni.change, 2) + ' (' + sign + number_format(ni.changePct, 2) + '%)</span></div>');
                });
                if(items.length > 0) {
                    track.innerHTML = items.join('') + items.join('');
                }
            }
        }
    }).catch(function(e){console.warn('NSE background fetch error:', e);});
}, 100);

function number_format(number, decimals) {
    if (isNaN(number)) return '0.00';
    return parseFloat(number).toFixed(decimals);
}
// =====================================================

var latestNewsId = <?= $latestNewsId ?>;
var allowNewsPopup = document.getElementById('allowNewsPopup');
var newNewsPopup = document.getElementById('newNewsPopup');
var newNewsTitleFallback = document.getElementById('newNewsTitleFallback');
var newNewsLinkFallback = document.getElementById('newNewsLinkFallback');
var btnCloseNewNews = document.getElementById('btnCloseNewNews');
var currentNewNewsId = 0;
var newsInterval;

// ★ v6.5: sw.js ফাইল নেই — service worker registration বাদ (পেজ-লেভেল Notification যথেষ্ট, localhost-এ কাজ করে)

function showSystemNotification(title, body, url) {
    if (!("Notification" in window) || Notification.permission !== "granted") return false;
    var options = {
        body: body,
        icon: 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="96" height="96"%3E%3Crect width="96" height="96" rx="18" fill="%23B71C1C"/%3E%3Ctext x="50%25" y="54%25" dominant-baseline="middle" text-anchor="middle" font-size="46" fill="%23fff" font-family="sans-serif"%3E%E0%A6%B8%E0%A6%82%3C/text%3E%3C/svg%3E',
        tag: 'new-news-' + url,
        data: { url: url }
    };
    try {
        // ★ v6.5: সরাসরি পেজ-লেভেল Notification (async SW-এর অপেক্ষায় আটকে থাকত না — এটাই ছিল মূল বাগ)
        var notification = new Notification(title, options);
        notification.onclick = function() {
            window.focus();
            window.location.href = url;
            notification.close();
        };
        return true;
    } catch (e) {
        console.error("Notification error:", e);
        return false;
    }
}

// ★ v6.8 FIX: আগে পপ-আপ আগেই খোলা থাকলে (যেমন লোড-পপ-আপ) নতুন খবরের পপ-আপ সম্পূর্ণ স্কিপ হয়ে যেত।
// এখন খোলা থাকলেও নতুন শিরোনাম আপডেট হবে ও হাইড টাইমার রিসেট হবে।
function showHtmlPopup(title, url) {
    if (!newNewsPopup) return;
    if (newNewsTitleFallback) newNewsTitleFallback.textContent = title;
    if (newNewsLinkFallback) newNewsLinkFallback.href = url;
    newNewsPopup.style.display = 'block';
    if (window.__nnPopupHideTimer) clearTimeout(window.__nnPopupHideTimer);
    window.__nnPopupHideTimer = setTimeout(function() {
        if (newNewsPopup) newNewsPopup.style.display = 'none';
    }, 10000);
}

if (btnCloseNewNews) {
    btnCloseNewNews.addEventListener('click', function() {
        if (newNewsPopup) newNewsPopup.style.display = 'none';
    });
}

// ★ v6.9 (সিস্টেম নোটিফিকেশন মোড): পেজ লোডের ৪ সেকেন্ড পর সর্বশেষ খবরটিও সিস্টেম
// নোটিফিকেশন (Windows টোস্ট) হিসেবে যাবে — ইউজারের নির্দেশে পেজের ভিতরের পপ-আপ বাদ।
// পারমিশন granted না হলে কিছুই দেখানো হবে না — পারমিশন পপ-আপ/বেল বাটন চালু করার পথ দেখাবে।
var NEWS_POPUP_ON_LOAD = <?= $NEWS_POPUP_ON_LOAD ? 'true' : 'false' ?>;
var latestNewsTitleJS = <?= $latestNewsTitleJS ?>;
function showLatestOnLoad() {
    if (!NEWS_POPUP_ON_LOAD || latestNewsId <= 0) return;
    var url = '?page=single&id=' + latestNewsId;
    var ok = showSystemNotification('নতুন খবর প্রকাশিত হয়েছে', latestNewsTitleJS, url);
    // Notification API-হীন খুব পুরোনো ব্রাউজারে ফলব্যাক — পেজের ভিতরে পপ-আপ
    if (!ok && !("Notification" in window)) showHtmlPopup(latestNewsTitleJS, url);
}
if (NEWS_POPUP_ON_LOAD) {
    setTimeout(function() {
        if (allowNewsPopup && allowNewsPopup.style.display === 'flex') {
            // পারমিশন পপ-আপ খোলা থাকলে সেটা বন্ধ হওয়া পর্যন্ত অপেক্ষা (সর্বোচ্চ ~১৫ সেকেন্ড)
            var waitTries = 0;
            var waitIv = setInterval(function() {
                waitTries++;
                if (!allowNewsPopup || allowNewsPopup.style.display !== 'flex' || waitTries > 10) {
                    clearInterval(waitIv);
                    showLatestOnLoad();
                }
            }, 1500);
        } else {
            showLatestOnLoad();
        }
    }, 4000);
}

var __nnChecking = false;
function checkForNewNews() {
    if (__nnChecking) return;
    __nnChecking = true;
    // ★ v6.8: t= cache-buster + cache:'no-store' — পুরোনো ক্যাশ হিট করে নতুন খবর মিস হওয়া বন্ধ
    fetch('?ajax_check_new_news=1&last_id=' + latestNewsId + '&t=' + Date.now(), { cache: 'no-store' })
    .then(function(response){ return response.text(); })
    .then(function(txt){
        __nnChecking = false;
        // ★ v6.8 FIX: response.json() নয় — text→JSON.parse try/catch, রেসপন্সে warning ঢুকে গেলেও
        // পুরো চেকার চুপচাপ মরে যাবে না
        var data = null;
        try { data = JSON.parse(txt); } catch (e) { console.warn('New-news check: invalid JSON response'); return; }
        if (data && data.status === 'new' && data.last_id > latestNewsId) {
            latestNewsId = data.last_id;
            var latestItem = data.items[data.items.length - 1];
            var url = '?page=single&id=' + latestItem.id;
            var newsTitle = (latestItem.title && String(latestItem.title).trim()) ? latestItem.title : 'নতুন খবর এসেছে';

            // ★ v6.9 (ইউজারের নির্দেশ: "need system Notifications"): শুধুমাত্র সিস্টেম
            // নোটিফিকেশন (Windows টোস্ট / Action Center) পাঠানো হবে — পেজের ভিতরের
            // পপ-আপ আর দেখানো হবে না। Notification API-হীন খুব পুরোনো ব্রাউজারেই
            // কেবল ফলব্যাক হিসেবে পেজ-পপ-আপ যাবে।
            var systemShown = showSystemNotification('নতুন খবর প্রকাশিত হয়েছে', newsTitle, url);
            if (!systemShown && !("Notification" in window)) {
                showHtmlPopup(newsTitle, url);
            }

            // টোস্ট পাঠানো গেলে ৬ সেকেন্ড পর পেজ রিফ্রেশ (নতুন খবর হোমপেজে চলে আসবে);
            // পারমিশন না থাকলে কিছুই পাঠানো হয়নি — অকারণ রিফ্রেশে বিভ্রান্তি হবে, তাই রিফ্রেশ নয়
            if (systemShown) {
                if (window.__nnReloadTimer) clearTimeout(window.__nnReloadTimer);
                window.__nnReloadTimer = setTimeout(function(){ window.location.reload(); }, 6000);
            }
        }
    }).catch(function(error){ __nnChecking = false; console.error('Error checking for new news:', error); });
}

function startNewsCheck() {
    if (!newsInterval) {
        checkForNewNews();
        newsInterval = setInterval(checkForNewNews, 20000);
        // ★ v6.8: ব্যাকগ্রাউন্ড ট্যাবে টাইমার থ্রটল হয় — ট্যাবে ফিরে এলেই/ফোকাসে সাথে সাথে চেক হবে
        document.addEventListener('visibilitychange', function(){ if (!document.hidden) checkForNewNews(); });
        window.addEventListener('focus', checkForNewNews);
    }
}

var newsAllowed = null;
try { newsAllowed = localStorage.getItem('news_popup_allowed'); } catch(e) {}

setTimeout(startNewsCheck, 5000);

// ★ v6.8 FIX: আগে একবার 'না' চাপলে আর কখনোই পারমিশন চাওয়া হত না — সিস্টেম নোটিফিকেশন
// চালু করার পথ স্থায়ীভাবে বন্ধ হয়ে যেত। এখন: ব্রাউজার-লেভেল পারমিশন 'default' থাকলে
// ১ ঘণ্টা পর পর আবার জিজ্ঞেস করা হবে; Chrome-এ সরাসরি ব্লক করা থাকলে আর চাওয়া হবে না।
function shouldAskNewsPermission() {
    if (!("Notification" in window)) return false;
    if (Notification.permission === 'granted') return false;
    if (Notification.permission === 'denied') return false;
    if (newsAllowed === 'granted') return false;
    if (newsAllowed === 'denied') {
        var deniedAt = 0;
        try { deniedAt = parseInt(localStorage.getItem('news_popup_denied_at'), 10) || 0; } catch(e) {}
        return (Date.now() - deniedAt) > 3600000; // ১ ঘণ্টা পর আবার সুযোগ
    }
    return true;
}
if (shouldAskNewsPermission()) {
    setTimeout(function() {
        if (allowNewsPopup) allowNewsPopup.style.display = 'flex';
    }, 2000);
}

// ★ v6.8: ভাসমান নোটিফিকেশন বেল (বাম-নিচে) — সিস্টেম নোটিফিকেশন চালু না থাকলে সবসময়
// দেখা যাবে; ক্লিক করলে পারমিশন পপ-আপ খুলবে। চালু হয়ে গেলে (granted) নিজে থেকেই লুকাবে।
var notifyBellBtn = document.getElementById('notifyBellBtn');
function refreshNotifyBell() {
    if (!notifyBellBtn) return;
    var showBell = ("Notification" in window) && Notification.permission === 'default';
    notifyBellBtn.style.display = showBell ? 'flex' : 'none';
}
refreshNotifyBell();
if (notifyBellBtn) {
    notifyBellBtn.addEventListener('click', function() {
        if (allowNewsPopup) allowNewsPopup.style.display = 'flex';
    });
}

var btnAllowYes = document.getElementById('btnAllowYes');
if (btnAllowYes) {
    btnAllowYes.addEventListener('click', function() {
        if (allowNewsPopup) allowNewsPopup.style.display = 'none';
        if (!("Notification" in window)) {
            try { localStorage.setItem('news_popup_allowed', 'granted'); } catch(e){}
            return;
        }
        Notification.requestPermission().then(function (permission) {
            if (permission === "granted") {
                try { localStorage.setItem('news_popup_allowed', 'granted'); } catch(e){}
                showSystemNotification('নোটিফিকেশন চালু হয়েছে!', 'এখন থেকে নতুন খবর প্রকাশিত হলে জানতে পারবেন।', '?page=home');
            } else {
                try { localStorage.setItem('news_popup_allowed', 'denied'); localStorage.setItem('news_popup_denied_at', String(Date.now())); } catch(e){}
            }
            if (typeof refreshNotifyBell === 'function') refreshNotifyBell();
        });
    });
}

var btnAllowNo = document.getElementById('btnAllowNo');
if (btnAllowNo) {
    btnAllowNo.addEventListener('click', function() {
        try { localStorage.setItem('news_popup_allowed', 'denied'); localStorage.setItem('news_popup_denied_at', String(Date.now())); } catch(e){}
        if (allowNewsPopup) allowNewsPopup.style.display='none';
    });
}

// ★ v6.8: টেস্ট মোড — ব্রাউজারে index.php?notify_test=1 খুললে সাথে সাথে একটা টেস্ট সিস্টেম
// নোটিফিকেশন পাঠানো হবে; Windows/Chrome যদি আটকে দায় তাহলে সেটাও মেসেজে জানানো হবে।
if (<?= isset($_GET['notify_test']) ? 'true' : 'false' ?>) {
    setTimeout(function() {
        if (!("Notification" in window)) { alert('এই ব্রাউজারে Notification সাপোর্ট নেই।'); return; }
        function __fireTestNotification() {
            var ok = showSystemNotification('টেস্ট নোটিফিকেশন', 'সিস্টেম নোটিফিকেশন কাজ করছে! নতুন খবর এলে এভাবেই জানতে পারবেন।', '?page=home');
            if (!ok) {
                alert('পারমিশন granted, তবুও নোটিফিকেশন দেখানো যায়নি।\n\nWindows Settings > System > Notifications এ Google Chrome চালু আছে কিনা এবং Focus Assist অফ আছে কিনা দেখুন।');
            }
        }
        if (Notification.permission === 'granted') { __fireTestNotification(); return; }
        if (Notification.permission === 'denied') { alert('নোটিফিকেশন ব্লক করা আছে। অ্যাড্রেসবারের পাশের আইকনে ক্লিক করে Notifications > Allow করুন।'); return; }
        Notification.requestPermission().then(function(p) {
            if (p === 'granted') { __fireTestNotification(); } else { alert('নোটিফিকেশন পারমিশন পাওয়া যায়নি: ' + p); }
        });
    }, 1000);
}
})();
</script>

<?php // ★ v2: TTS widget JS — শুধুমাত্র single news page-এ লোড হবে।
if ($page === 'single' && !empty($singleNews)): ?>
<!-- ====== BENGALI FEMALE ANCHOR SCRIPT (TTS News Reader) ====== -->
<script>
(function(){
    'use strict';

    var fab       = document.getElementById('bnAnchorFab');
    var panel    = document.getElementById('bnAnchorPanel');
    var closeBtn = document.getElementById('bnAnchorClose');
    var avatar   = document.getElementById('bnAnchorAvatar');
    var statusEl = document.getElementById('bnAnchorStatus');
    var statusTxt= document.getElementById('bnStatusText');
    var readBtn  = document.getElementById('bnReadBtn');
    var stopBtn  = document.getElementById('bnStopBtn');
    var hideBtn  = document.getElementById('bnHideBtn');
    var panelHideBtn = document.getElementById('bnPanelHideBtn');
    var preview  = document.getElementById('bnTextPreview');
    var textWrap = document.getElementById('bnTextWrap');
    var textToggle = document.getElementById('bnTextToggle');

    // ===== Toggle: show/hide the Bengali text being read =====
    // Both the small eye-icon in the text-wrap header AND the prominent লুকান button sync.
    var textCollapsed = false;
    function applyTextCollapse() {
        if (textCollapsed) {
            textWrap.classList.add('collapsed');
            if (textToggle) textToggle.innerHTML = '<i class="fas fa-eye-slash"></i>';
            if (textToggle) textToggle.title = 'টেক্সট দেখান';
            if (hideBtn)    hideBtn.innerHTML = '<i class="fas fa-eye"></i> দেখান';
            if (hideBtn)    hideBtn.title = 'টেক্সট দেখান';
        } else {
            textWrap.classList.remove('collapsed');
            if (textToggle) textToggle.innerHTML = '<i class="fas fa-eye"></i>';
            if (textToggle) textToggle.title = 'টেক্সট লুকান';
            if (hideBtn)    hideBtn.innerHTML = '<i class="fas fa-eye-slash"></i> লুকান';
            if (hideBtn)    hideBtn.title = 'টেক্সট লুকান';
        }
    }
    function toggleText() { textCollapsed = !textCollapsed; applyTextCollapse(); }
    if (textToggle) textToggle.addEventListener('click', toggleText);
    if (hideBtn)    hideBtn.addEventListener('click', toggleText);

    // ===== Voice Engine: Prefer native Bengali voice (Web Speech API), fall back to Google TTS proxy =====
    var bnVoice = null;
    var speechReady = false;
    function loadBengaliVoice() {
        if (!('speechSynthesis' in window)) { speechReady = true; return; }
        var voices = window.speechSynthesis.getVoices() || [];
        // Match any Bengali voice (bn, bn-IN, bn-BD)
        var bnVoices = voices.filter(function(v){
            var L = (v.lang || '').toLowerCase();
            return L === 'bn' || L === 'bn-in' || L === 'bn-bd' || L.indexOf('bn-') === 0 || L.indexOf('bn_') === 0;
        });
        // HD female voice preference list (neural/premium Bengali female voices).
        // Order = preference. Tanishaa is Azure neural HD voice (24kHz).
        // HD female Bengali voice names — priority order.
        // 'tuli' = ElevenLabs Bengali HD female voice (when configured server-side).
        var FEMALE_HD_NAMES = ['tuli','annwesha','anwesha','tanishaa','kalpana',
                                'aditi','saraswati','lipsa','tara','sara','swapna',
                                'arnab','ananya','female','woman','google বাংলা'];
        var female = null;
        // First pass: pick the highest-priority HD female voice available
        for (var fi = 0; fi < FEMALE_HD_NAMES.length && !female; fi++) {
            female = bnVoices.find(function(v){
                var n = (v.name || '').toLowerCase();
                return n.indexOf(FEMALE_HD_NAMES[fi]) >= 0;
            });
        }
        // Fallback: any voice whose name explicitly mentions female
        if (!female) {
            female = bnVoices.find(function(v){
                var n = (v.name || '').toLowerCase();
                return n.indexOf('female') >= 0 || n.indexOf('woman') >= 0
                    || n.indexOf('nadira') >= 0 || n.indexOf('diya') >= 0;
            });
        }
        bnVoice = female || bnVoices[0] || null;
        speechReady = true;
    }
    loadBengaliVoice();
    if ('speechSynthesis' in window) {
        try { window.speechSynthesis.onvoiceschanged = loadBengaliVoice; } catch(e){}
        // Some browsers populate voices a moment later; retry once.
        setTimeout(loadBengaliVoice, 600);
    }

    // Update the voice badge dynamically based on detected engine
    function refreshVoiceBadge() {
        var badge = document.getElementById('bnVoiceBadge');
        if (!badge) return;
        var span = badge.querySelector('span');
        if (!span) return;
        if (bnVoice) {
            // Show the actual voice name (cleaned up) + native indicator
            var nm = bnVoice.name || 'বাংলা নারী কণ্ঠ';
            // Strip language tags like "Microsoft Hemant - Bengali (India)" → "Hemant"
            var clean = nm.replace(/^(Microsoft|Google|Apple)\s+/i,'').replace(/\s*-\s*Bengali.*$/i,'').replace(/\s*\(.*\)\s*$/,'').trim();
            span.textContent = clean + ' — অরিজিনাল বাংলা';
            badge.title = 'ব্যবহৃত ভয়েস: ' + nm + ' (' + (bnVoice.lang || 'bn') + ')';
        } else {
            span.textContent = 'Google বাংলা নারী কণ্ঠ';
            badge.title = 'আপনার ডিভাইসে অরিজিনাল বাংলা ভয়েস নেই — Google TTS ব্যবহৃত হচ্ছে';
        }
    }
    setTimeout(refreshVoiceBadge, 700);

    // Play one chunk via the best available engine.
    // Returns an object with a .cancel() method to stop playback.
    function playChunk(text, onEnd, onError) {
        // ----- Path A: Native Bengali voice (Web Speech API) -- HD on supported devices -----
        if (bnVoice && 'speechSynthesis' in window) {
            try {
                var u = new SpeechSynthesisUtterance(text);
                u.voice = bnVoice;
                u.lang  = bnVoice.lang || 'bn-IN';
                // HD-quality female voice tuning (a bit faster, per user request):
                //   rate 1.25  -> slightly faster than previous 1.15
                //   pitch 1.05 -> small lift = warmer, more feminine timbre
                //   volume 1.0 -> full volume
                u.rate  = 1.25;
                u.pitch = 1.05;
                u.volume = 1.0;
                var ended = false;
                u.onend = function(){ if (ended) return; ended = true; onEnd(); };
                u.onerror = function(){ if (ended) return; ended = true; onError(); };
                // Cancel anything queued, then speak
                try { window.speechSynthesis.cancel(); } catch(e){}
                window.speechSynthesis.speak(u);
                return {
                    engine: 'webspeech',
                    cancel: function(){ try { window.speechSynthesis.cancel(); } catch(e){} }
                };
            } catch (e) { /* fall through to proxy */ }
        }
        // ----- Path B: HD TTS proxy (Murf Anwesha -> Azure -> Google gtx -> tw-ob) -----
        // playbackRate = 1.25 (a bit faster, per user request).
        // The proxy caches audio on the server, so repeat plays are instant.
        var url = '?tts_proxy=1&text=' + encodeURIComponent(text);
        var a = new Audio(url);
        try { a.playbackRate = 1.25; } catch(e){}  // a bit faster than previous 1.15
        a.onended  = onEnd;
        a.onerror  = onError;
        var p = a.play();
        if (p && typeof p.then === 'function') {
            p.catch(function(err){ onError(err); });
        }
        return { engine: 'proxy', cancel: function(){ try { a.pause(); } catch(e){} } };
    }

    if (!fab || !panel) return;

    // ===== State =====
    var isSpeaking = false;
    var chunks = [];
    var chunkIdx = 0;
    var currentAudio = null;
    var mouthTimer = null;
    var lastFetchedText = '';

    // ===== Helpers =====
    function setStatus(text, state) {
        if (statusTxt) statusTxt.textContent = text;
        statusEl.className = 'bn-anchor-status ' + (state || '');
    }

    function showPanel() {
        panel.classList.add('open');
        fab.classList.add('hidden');
        // Panel visible again — clear the FAB playing badge (status shows in panel now)
        fab.classList.remove('fab-playing');
    }
    function hidePanel() {
        panel.classList.remove('open');
        fab.classList.remove('hidden');
        // ★ If audio is still playing, show a live badge on FAB so user knows voice is running
        if (isSpeaking) fab.classList.add('fab-playing');
    }

    fab.addEventListener('click', showPanel);
    // ★ Hide button & close button: just hide panel — audio keeps playing (per user request)
    if (closeBtn) closeBtn.addEventListener('click', function(){ hidePanel(); });
    if (panelHideBtn) panelHideBtn.addEventListener('click', function(){ hidePanel(); });

    // ===== Split Bengali text into TTS-safe chunks (Google limit ~140 chars) =====
    function splitIntoChunks(text, maxLen) {
        if (!text) return [];
        // Sentence boundaries for Bengali: । ॥ ? ! .
        var sentences = text.match(/[^.!?।॥\n]+[.!?।॥]*\s*/g) || [text];
        var out = [];
        var cur = '';
        for (var i = 0; i < sentences.length; i++) {
            var s = sentences[i];
            if (s.length > maxLen) {
                if (cur) { out.push(cur.trim()); cur = ''; }
                // Word-split long sentences
                var words = s.split(/\s+/);
                var wc = '';
                for (var j = 0; j < words.length; j++) {
                    if ((wc + ' ' + words[j]).length > maxLen) {
                        if (wc) out.push(wc.trim());
                        wc = words[j];
                    } else { wc += ' ' + words[j]; }
                }
                if (wc) cur = wc;
            } else if ((cur + s).length > maxLen) {
                if (cur) out.push(cur.trim());
                cur = s;
            } else {
                cur += s;
            }
        }
        if (cur) out.push(cur.trim());
        return out.filter(function(c){ return c.length > 0; });
    }

    function setSpeaking(on) {
        isSpeaking = on;
        if (on) avatar.classList.add('speaking');
        else avatar.classList.remove('speaking');
        readBtn.disabled = on;
        stopBtn.disabled = !on;
        readBtn.innerHTML = on ? '<i class="fas fa-spinner fa-spin"></i> পড়া হচ্ছে…' : '<i class="fas fa-play"></i> সংবাদ শুনুন';
        // ★ Sync FAB live badge: show only when panel is hidden but audio is still playing
        if (on && !panel.classList.contains('open')) fab.classList.add('fab-playing');
        else if (!on) fab.classList.remove('fab-playing');
    }

    function stopSpeaking() {
        isSpeaking = false;
        chunks = [];
        chunkIdx = 0;
        if (currentAudio) {
            try { currentAudio.cancel && currentAudio.cancel(); } catch(e){}
            try { currentAudio.pause && currentAudio.pause(); } catch(e){}
            currentAudio = null;
        }
        if ('speechSynthesis' in window) { try { window.speechSynthesis.cancel(); } catch(e){} }
        if (mouthTimer) { clearInterval(mouthTimer); mouthTimer = null; }
        setSpeaking(false);
        setStatus('থামানো হয়েছে। আবার শুনতে ক্লিক করুন।', 'paused');
    }

    // ===== Play next chunk via best available engine (Web Speech API first, Google TTS proxy as fallback) =====
    function playNext() {
        if (!isSpeaking) return;
        if (chunkIdx >= chunks.length) {
            setSpeaking(false);
            // ★ After playback: text stays visible; user can hide text (লুকান) or hide whole panel (হাইড)
            var engineLabel = bnVoice
                ? 'অরিজিনাল বাংলা ভয়েসে পড়া শেষ। প্যানেল হাইড করতে “হাইড”, টেক্সট লুকাতে “লুকান” বোতামে ক্লিক করুন।'
                : 'সংবাদ শেষ। প্যানেল হাইড করতে “হাইড”, টেক্সট লুকাতে “লুকান” বোতামে ক্লিক করুন।';
            setStatus(engineLabel, 'paused');
            // Only the active highlight is cleared — text remains visible
            var allChunks = preview ? preview.querySelectorAll('.bn-chunk.bn-chunk-active') : [];
            for (var ac = 0; ac < allChunks.length; ac++) allChunks[ac].classList.remove('bn-chunk-active');
            return;
        }
        var text = chunks[chunkIdx];
        if (!text) { chunkIdx++; playNext(); return; }

        // Highlight the currently-reading chunk in the full text preview
        if (preview) {
            var prevActive = preview.querySelector('.bn-chunk.bn-chunk-active');
            if (prevActive) { prevActive.classList.remove('bn-chunk-active'); prevActive.classList.add('bn-chunk-done'); }
            var activeEl = preview.querySelector('.bn-chunk[data-idx="' + chunkIdx + '"]');
            if (activeEl) {
                activeEl.classList.add('bn-chunk-active');
                activeEl.classList.remove('bn-chunk-done');
                // Scroll the active chunk into view (centered)
                try {
                    var pH = preview.getBoundingClientRect().height;
                    var aH = activeEl.getBoundingClientRect().height;
                    preview.scrollTop = preview.scrollTop + (activeEl.getBoundingClientRect().top - preview.getBoundingClientRect().top) - (pH - aH) / 2;
                } catch(e){}
            }
        }

        currentAudio = playChunk(
            text,
            function onEnd() {                       // success → next chunk
                chunkIdx++;
                setTimeout(playNext, 50);
            },
            function onError(err) {                   // skip problematic chunk, keep going
                console.warn('[BN Anchor] chunk error, skipping:', err);
                chunkIdx++;
                setTimeout(playNext, 50);
            }
        );
    }

    // ===== Fetch latest news (existing endpoint) for homepage =====
    function fetchLatestNewsText(cb) {
        setStatus('সর্বশেষ সংবাদ আনা হচ্ছে…', 'paused');
        try {
            fetch('?action=api_latest_news&t=' + Date.now(), { cache: 'no-store' })
                .then(function(r){ return r.json(); })
                .then(function(data){
                    if (!data || data.error || !data.title) {
                        // Fallback: try breaking news ticker text
                        var bnText = '';
                        var firstBreak = document.querySelector('.breaking-track span');
                        if (firstBreak) bnText = firstBreak.textContent.trim();
                        if (!bnText) {
                            // Last resort: site tagline + h1
                            var h1 = document.querySelector('.kol-header h1');
                            if (h1) bnText = h1.textContent + '। স্বাগতম।';
                        }
                        if (bnText) cb(bnText);
                        else cb(null);
                        return;
                    }
                    var title = (data.title || '').trim();
                    var slug  = (data.slug || '').trim();
                    var fullText = 'আপনাদের স্বাগতম জানাই। সংবাদ সংকলনের সর্বশেষ খবর। ' + title + (slug ? '। ' + slug : '');
                    cb(fullText);
                })
                .catch(function(){
                    // Try breaking news fallback
                    var firstBreak = document.querySelector('.breaking-track span');
                    if (firstBreak) cb(firstBreak.textContent.trim());
                    else cb(null);
                });
        } catch (e) { cb(null); }
    }

    // ===== On single news page: read the article content =====
    function getCurrentArticleText() {
        var titleEl = document.querySelector('.news-title');
        var headlineEl = document.querySelector('.single-headline');
        var contentEl = document.querySelector('.single-content');
        if (!titleEl && !contentEl) return null;

        var text = '';
        if (titleEl) text += titleEl.textContent + '. ';
        if (headlineEl) text += headlineEl.textContent + '. ';

        if (contentEl) {
            var nodes = contentEl.querySelectorAll('p, h2, h3, h4, li');
            for (var i = 0; i < nodes.length; i++) {
                var n = nodes[i];
                if (n.closest('.ad-wrapper') || n.closest('.news-video-wrapper') || n.closest('.twitter-embed-wrapper') || n.closest('.ad-label')) continue;
                text += n.textContent + '. ';
            }
        }
        return text.trim();
    }

    // ===== Main read button =====
    readBtn.addEventListener('click', function(){
        if (isSpeaking) { stopSpeaking(); return; }

        // Try current article first (single news page)
        var articleText = getCurrentArticleText();
        if (articleText && articleText.length > 10) {
            startReading(articleText, 'চলতি সংবাদ পড়া হচ্ছে…');
            return;
        }

        // Otherwise fetch latest news
        fetchLatestNewsText(function(text){
            if (!text || !text.trim()) {
                setStatus('কোনো সংবাদ পাওয়া যায়নি।', 'paused');
                return;
            }
            // Avoid re-reading the same text twice in a row
            if (lastFetchedText === text) {
                text = 'আপনি এইমাত্র শেষ সংবাদটি শুনেছেন। ' + text;
            }
            lastFetchedText = text;
            startReading(text, 'সর্বশেষ সংবাদ পড়া হচ্ছে…');
        });
    });

    function startReading(fullText, statusMsg) {
        setStatus(statusMsg, 'reading');
        // Chunk size 200 chars (matches PHP proxy limit). Fewer chunks = smoother flow.
        chunks = splitIntoChunks(fullText, 200);
        chunkIdx = 0;
        if (chunks.length === 0) {
            setStatus('পড়ার মতো টেক্সট নেই।', 'paused');
            return;
        }
        // Render the full Bengali text with per-chunk spans for highlighting
        if (preview) {
            var html = '';
            for (var ci = 0; ci < chunks.length; ci++) {
                html += '<span class="bn-chunk" data-idx="' + ci + '">' + escapeForHtml(chunks[ci]) + '</span>' + (ci < chunks.length - 1 ? ' ' : '');
            }
            preview.innerHTML = html;
            preview.scrollTop = 0;
        }
        if (textWrap) textWrap.style.display = 'block';
        setSpeaking(true);
        playNext();
    }

    function escapeForHtml(s){
        if(!s) return '';
        return String(s).replace(/[&<>"]/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]; });
    }

    stopBtn.addEventListener('click', stopSpeaking);

    // ===== Keyboard shortcut: Ctrl+Shift+A toggles anchor =====
    document.addEventListener('keydown', function(e){
        if (e.ctrlKey && e.shiftKey && (e.key === 'A' || e.key === 'a')) {
            e.preventDefault();
            if (panel.classList.contains('open')) {
                stopSpeaking();
                hidePanel();
            } else {
                showPanel();
            }
        }
        // Esc closes panel & stops
        if (e.key === 'Escape' && panel.classList.contains('open')) {
            stopSpeaking();
            hidePanel();
        }
    });

    // ===== Auto-pause when tab is hidden, resume on focus =====
    document.addEventListener('visibilitychange', function(){
        if (document.hidden && isSpeaking && currentAudio) {
            try { currentAudio.pause(); } catch(e){}
            setStatus('ট্যাব ব্যাকগ্রাউন্ডে — সাময়িক বিরতি।', 'paused');
        } else if (!document.hidden && isSpeaking && currentAudio && currentAudio.paused) {
            try { currentAudio.play().catch(function(){}); setStatus('চালু হচ্ছে…', 'reading'); } catch(e){}
        }
    });

    // Show FAB on first scroll past 200px on home page (optional UX hint)
    var hasPulsed = false;
    window.addEventListener('scroll', function(){
        if (!hasPulsed && window.scrollY > 200) {
            hasPulsed = true;
            // Brief attention pulse — already pulsing by default, no-op
        }
    }, { passive: true });

    // ============================================================
    //   NO AUTOPLAY — user must click "শুনুন" button to start.
    //   Panel still opens automatically on single news pages so the
    //   button is right there; the female anchor only starts speaking
    //   when the user explicitly taps শুনুন.
    // ============================================================

    console.log('[BN Anchor] Bengali female anchor TTS widget loaded. (manual play)');
})();
</script>
<!-- ====== END BENGALI FEMALE ANCHOR SCRIPT ====== -->
<?php endif; // end single-news-only widget JS ?>

</body>
</html>