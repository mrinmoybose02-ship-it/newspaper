<?php
// ============================================================
// admin_epaper.php — ই-পেপার PDF আপলোড/ডিলিট পেজ (অ্যাডমিন)
// ============================================================
// কীভাবে চালু করবেন:
//   index.php-তে $otherAdminPages অ্যারে-তে 'admin_epaper' যোগ করুন, যেমন:
//     $otherAdminPages = ['admin_dashboard','admin_add','admin_edit','admin_manage',
//                        'admin_breaking','admin_categories','admin_cat_edit',
//                        'admin_password','admin_epaper'];
//   তারপর ?page=admin_epaper খুললে এই পেজ আসবে।
//
//   অথবা সরাসরি admin_epaper.php ওয়েবে খুললেও চলবে (সেশন চেক সহ)।
// ============================================================

// সরাসরি অ্যাক্সেস হলে config.php ও সেশন চেক করা
if (!function_exists('loadSettings')) {
    $configFile = __DIR__ . '/config.php';
    if (file_exists($configFile)) {
        require_once $configFile;
    }
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    date_default_timezone_set('Asia/Kolkata');
    try { if (isset($pdo)) $pdo->exec("SET time_zone = '+05:30';"); } catch (Throwable $e) {}
}

// সেশন চেক — লগইন না থাকলে লগইন পেজে পাঠানো
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?page=admin_login');
    exit;
}

// ====== কনফিগারেশন ======
$EPAPER_DIR     = __DIR__ . '/epaper';
$EPAPER_URL_BASE = 'epaper';
$MAX_SIZE       = 50 * 1024 * 1024; // 50 MB

// ফোল্ডার তৈরি না থাকলে তৈরি করা
if (!is_dir($EPAPER_DIR)) {
    @mkdir($EPAPER_DIR, 0755, true);
}

// বাংলা হেল্পার
$bnDigits = ['০','১','২','৩','৪','৫','৬','৭','৮','৯'];
$enDigits = ['0','1','2','3','4','5','6','7','8','9'];
$bnMonths = [1=>'জানুয়ারি',2=>'ফেব্রুয়ারি',3=>'মার্চ',4=>'এপ্রিল',5=>'মে',6=>'জুন',7=>'জুলাই',8=>'আগস্ট',9=>'সেপ্টেম্বর',10=>'অক্টোবর',11=>'নভেম্বর',12=>'ডিসেম্বর'];
function epa_toBn($s){ global $bnDigits,$enDigits; return str_replace($enDigits,$bnDigits,(string)$s); }
function epa_fmtSize($b){ if($b<1024) return $b.' B'; if($b<1048576) return round($b/1024,1).' KB'; return round($b/1048576,2).' MB'; }
function epa_fmtDate($s){ global $bnMonths; try{ $d=new DateTime($s); return $d->format('d').' '.$bnMonths[(int)$d->format('m')].' '.$d->format('Y'); }catch(Throwable $e){ return $s; } }

// সাইট টাইটল
$siteTitle = 'সংবাদ সংকলন';
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $st = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key='site_title' LIMIT 1");
        $st->execute();
        $r = $st->fetchColumn();
        if ($r) $siteTitle = $r;
    }
} catch (Throwable $e) {}

// ====== POST অ্যাকশন: আপলোড / ডিলিট ======
$flashMsg = '';
$flashType = ''; // success | error

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ----- আপলোড -----
    if ($action === 'upload') {
        $dateInput = trim($_POST['epaper_date'] ?? '');
        $replaceExisting = isset($_POST['replace_existing']);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateInput)) {
            $flashMsg = 'তারিখ সঠিক নয়। YYYY-MM-DD ফরম্যাটে দিন।';
            $flashType = 'error';
        } elseif (!isset($_FILES['epaper_file']) || $_FILES['epaper_file']['error'] !== UPLOAD_ERR_OK) {
            $err = $_FILES['epaper_file']['error'] ?? UPLOAD_ERR_NO_FILE;
            $msgs = [
                UPLOAD_ERR_INI_SIZE   => 'ফাইল php.ini-এর upload_max_filesize ছাড়িয়ে গেছে।',
                UPLOAD_ERR_FORM_SIZE  => 'ফাইল সর্বোচ্চ সাইজ ছাড়িয়ে গেছে।',
                UPLOAD_ERR_PARTIAL    => 'ফাইল আংশিক আপলোড হয়েছে।',
                UPLOAD_ERR_NO_FILE    => 'কোনো ফাইল নির্বাচন করা হয়নি।',
                UPLOAD_ERR_NO_TMP_DIR => 'সার্ভারে টেম্প ফোল্ডার নেই।',
                UPLOAD_ERR_CANT_WRITE => 'ডিস্কে লেখা যাচ্ছে না।',
                UPLOAD_ERR_EXTENSION  => 'PHP এক্সটেনশন আপলোড আটকে দিয়েছে।',
            ];
            $flashMsg = $msgs[$err] ?? 'আপলোডে অজানা সমস্যা।';
            $flashType = 'error';
        } else {
            $file = $_FILES['epaper_file'];
            if ($file['size'] > $MAX_SIZE) {
                $flashMsg = 'ফাইল সাইজ 50 MB-এর বেশি। ছোট ফাইল দিন।';
                $flashType = 'error';
            } else {
                // MIME যাচাই — PDF হতেই হবে
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($file['tmp_name']);
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $isPdf = ($mime === 'application/pdf') || ($ext === 'pdf');
                if (!$isPdf) {
                    $flashMsg = 'শুধুমাত্র PDF ফাইল আপলোড করা যাবে। (আপনার ফাইল: ' . htmlspecialchars($mime ?: 'অজানা') . ')';
                    $flashType = 'error';
                } else {
                    $targetPath = $EPAPER_DIR . '/' . $dateInput . '.pdf';
                    if (file_exists($targetPath) && !$replaceExisting) {
                        $flashMsg = 'এই তারিখের ই-পেপার আগে থেকেই আছে। প্রতিস্থাপন করতে "বিদ্যমান ফাইল প্রতিস্থাপন করুন" চেক করুন।';
                        $flashType = 'error';
                    } elseif (move_uploaded_file($file['tmp_name'], $targetPath)) {
                        $flashMsg = '✓ ই-পেপার সফলভাবে আপলোড হয়েছে: ' . epa_toBn(epa_fmtDate($dateInput));
                        $flashType = 'success';
                    } else {
                        $flashMsg = 'ফাইল সেভ করতে ব্যর্থ। epaper/ ফোল্ডারের পারমিশন (755) যাচাই করুন।';
                        $flashType = 'error';
                    }
                }
            }
        }
    }

    // ----- ডিলিট -----
    elseif ($action === 'delete') {
        $delDate = trim($_POST['del_date'] ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $delDate)) {
            $targetPath = $EPAPER_DIR . '/' . $delDate . '.pdf';
            // Path traversal সুরক্ষা
            $realTarget = realpath($targetPath);
            $realBase   = realpath($EPAPER_DIR);
            if ($realTarget && $realBase && strpos($realTarget, $realBase) === 0 && file_exists($realTarget)) {
                if (@unlink($realTarget)) {
                    $flashMsg = '✓ ই-পেপার ডিলিট করা হয়েছে: ' . epa_toBn(epa_fmtDate($delDate));
                    $flashType = 'success';
                } else {
                    $flashMsg = 'ডিলিট করতে ব্যর্থ। ফাইল পারমিশন যাচাই করুন।';
                    $flashType = 'error';
                }
            } else {
                $flashMsg = 'ফাইল পাওয়া যায়নি।';
                $flashType = 'error';
            }
        } else {
            $flashMsg = 'অবৈধ তারিখ।';
            $flashType = 'error';
        }
    }
}

// ====== সব উপলব্ধ ই-পেপার তালিকা ======
$allEpapers = [];
if (is_dir($EPAPER_DIR)) {
    foreach (glob($EPAPER_DIR . '/*.pdf') as $file) {
        $basename = basename($file, '.pdf');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $basename)) {
            $allEpapers[$basename] = [
                'file'  => basename($file),
                'path'  => $EPAPER_URL_BASE . '/' . rawurlencode(basename($file)),
                'size'  => filesize($file),
                'size_fmt' => epa_fmtSize(filesize($file)),
                'mtime' => filemtime($file),
            ];
        }
    }
}
krsort($allEpapers);

$totalFiles = count($allEpapers);
$totalSize = array_sum(array_column($allEpapers, 'size'));
$todayStr = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="bn" dir="ltr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>ই-পেপার ম্যানেজমেন্ট | <?= htmlspecialchars($siteTitle) ?> অ্যাডমিন</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@300;400;500;600;700&family=Noto+Serif+Bengali:wght@400;600;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{--red:#B71C1C;--red-dark:#7F0000;--gold:#D4950A;--bg:#F4F1EB;--fg:#1A1A1A;--muted:#666;--card:#FFF;--border:#DDD5C8;--nav-bg:#1A1A1A;--green:#2E7D32;--green-dark:#1B5E20}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Hind Siliguri',sans-serif;background:var(--bg);color:var(--fg);line-height:1.6;min-height:100vh}
a{color:inherit;text-decoration:none}

.adm-top{background:var(--nav-bg);color:#fff;padding:12px 0;border-bottom:3px solid var(--red)}
.adm-top .container{max-width:1100px;margin:0 auto;padding:0 16px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}
.adm-top h1{font-family:'Noto Serif Bengali',serif;font-size:20px;font-weight:900;display:flex;align-items:center;gap:10px}
.adm-top h1 i{color:var(--gold)}
.adm-top .links{display:flex;gap:8px;flex-wrap:wrap}
.adm-top .links a{display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,.08);color:#fff;border:1px solid rgba(255,255,255,.18);padding:6px 12px;border-radius:5px;font-size:12px;font-weight:600;transition:all .2s}
.adm-top .links a:hover{background:var(--gold);color:#000;border-color:var(--gold)}

.container{max-width:1100px;margin:0 auto;padding:0 16px}

.adm-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin:20px 0}
.stat-card{background:var(--card);border:1px solid var(--border);border-radius:8px;padding:16px 18px;display:flex;align-items:center;gap:14px}
.stat-card .si{width:42px;height:42px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:18px;color:#fff;flex-shrink:0}
.stat-card.s1 .si{background:var(--red)}
.stat-card.s2 .si{background:var(--green)}
.stat-card.s3 .si{background:var(--gold);color:#000}
.stat-card .sn{flex:1;min-width:0}
.stat-card .sv{font-family:'Noto Serif Bengali',serif;font-size:22px;font-weight:900;color:var(--fg);line-height:1.2}
.stat-card .sl{font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-top:2px}

.card{background:var(--card);border:1px solid var(--border);border-radius:8px;overflow:hidden;margin-bottom:24px}
.card-head{background:var(--nav-bg);color:#fff;padding:12px 18px;border-bottom:2px solid var(--red);display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}
.card-head h2{font-family:'Noto Serif Bengali',serif;font-size:16px;font-weight:700;display:flex;align-items:center;gap:8px}
.card-head h2 i{color:var(--gold)}
.card-body{padding:20px}

.flash{padding:12px 16px;border-radius:6px;margin-bottom:16px;font-size:14px;font-weight:600;display:flex;align-items:center;gap:10px}
.flash.success{background:#E8F5E9;color:var(--green-dark);border:1px solid #A5D6A7}
.flash.error{background:#FFEBEE;color:var(--red-dark);border:1px solid #EF9A9A}

.form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px}
.form-field{display:flex;flex-direction:column;gap:6px}
.form-field label{font-size:13px;font-weight:600;color:var(--fg)}
.form-field label .req{color:var(--red)}
.form-field input[type="date"],
.form-field input[type="file"]{padding:10px 12px;border:1px solid var(--border);border-radius:5px;font-size:14px;font-family:inherit;background:#fff;transition:border-color .2s}
.form-field input:focus{outline:none;border-color:var(--red);box-shadow:0 0 0 3px rgba(183,28,28,.1)}
.form-hint{font-size:11px;color:var(--muted);margin-top:2px}
.checkbox-row{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--fg);margin:8px 0 16px}
.checkbox-row input{width:16px;height:16px;cursor:pointer;accent-color:var(--red)}
.btn-submit{display:inline-flex;align-items:center;gap:8px;background:var(--red);color:#fff;border:none;padding:11px 24px;border-radius:5px;font-size:14px;font-weight:700;cursor:pointer;transition:all .2s;font-family:inherit}
.btn-submit:hover{background:var(--red-dark);transform:translateY(-1px);box-shadow:0 4px 12px rgba(183,28,28,.3)}

.empty-state{padding:40px 20px;text-align:center;color:var(--muted)}
.empty-state i{font-size:48px;color:var(--border);margin-bottom:12px;display:block}
.empty-state h3{font-family:'Noto Serif Bengali',serif;font-size:16px;color:var(--fg);margin-bottom:6px}
.empty-state p{font-size:13px}

.table-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;font-size:13px}
thead th{background:#fafaf7;text-align:left;padding:10px 14px;font-weight:700;color:var(--fg);border-bottom:2px solid var(--border);font-size:12px;text-transform:uppercase;letter-spacing:.4px;white-space:nowrap}
tbody td{padding:10px 14px;border-bottom:1px solid var(--border);vertical-align:middle}
tbody tr:hover{background:#fafaf7}
tbody tr:last-child td{border-bottom:none}
.date-cell{font-weight:700;color:var(--red);font-family:'Noto Serif Bengali',serif;font-size:14px}
.size-cell{color:var(--muted);font-size:12px}
.action-cell{white-space:nowrap;text-align:right}
.btn-sm{display:inline-flex;align-items:center;gap:5px;padding:6px 11px;border-radius:4px;font-size:11px;font-weight:600;cursor:pointer;border:none;font-family:inherit;transition:all .15s;text-decoration:none}
.btn-view{background:rgba(33,150,243,.1);color:#1976D2;border:1px solid rgba(33,150,243,.3)}
.btn-view:hover{background:#1976D2;color:#fff;border-color:#1976D2}
.btn-del{background:rgba(183,28,28,.08);color:var(--red);border:1px solid rgba(183,28,28,.25)}
.btn-del:hover{background:var(--red);color:#fff;border-color:var(--red)}
.btn-today{background:rgba(212,149,10,.12);color:#B8860B;border:1px solid rgba(212,149,10,.35)}
.btn-today:hover{background:var(--gold);color:#000;border-color:var(--gold)}

@media(max-width:640px){
    .form-row{grid-template-columns:1fr}
    .adm-top .container{flex-direction:column;align-items:flex-start;gap:10px}
    .card-body{padding:14px}
    thead th,tbody td{padding:8px 10px;font-size:12px}
}
</style>
</head>
<body>

<div class="adm-top">
<div class="container">
<h1><i class="far fa-newspaper"></i> ই-পেপার ম্যানেজমেন্ট</h1>
<div class="links">
<a href="epaper.php" target="_blank"><i class="fas fa-external-link-alt"></i> ই-পেপার দেখুন</a>
<a href="index.php?page=admin_dashboard"><i class="fas fa-tachometer-alt"></i> ড্যাশবোর্ড</a>
<a href="index.php?page=home"><i class="fas fa-home"></i> সাইট</a>
</div>
</div>
</div>

<div class="container">

<!-- পরিসংখ্যান -->
<div class="adm-stats">
<div class="stat-card s1">
<div class="si"><i class="far fa-file-pdf"></i></div>
<div class="sn"><div class="sv"><?= epa_toBn((string)$totalFiles) ?></div><div class="sl">মোট ই-পেপার</div></div>
</div>
<div class="stat-card s2">
<div class="si"><i class="fas fa-database"></i></div>
<div class="sn"><div class="sv"><?= epa_fmtSize($totalSize) ?></div><div class="sl">মোট সাইজ</div></div>
</div>
<div class="stat-card s3">
<div class="si"><i class="far fa-calendar-alt"></i></div>
<div class="sn"><div class="sv"><?= epa_toBn(epa_fmtDate($todayStr)) ?></div><div class="sl">আজকের তারিখ</div></div>
</div>
</div>

<?php if ($flashMsg): ?>
<div class="flash <?= htmlspecialchars($flashType) ?>">
<i class="fas fa-<?= $flashType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
<span><?= $flashMsg ?></span>
</div>
<?php endif; ?>

<!-- আপলোড ফর্ম -->
<div class="card">
<div class="card-head">
<h2><i class="fas fa-cloud-upload-alt"></i> নতুন ই-পেপার আপলোড</h2>
</div>
<div class="card-body">
<form method="POST" action="" enctype="multipart/form-data">
<input type="hidden" name="action" value="upload">

<div class="form-row">
<div class="form-field">
<label for="epaper_date">ই-পেপারের তারিখ <span class="req">*</span></label>
<input type="date" id="epaper_date" name="epaper_date" value="<?= htmlspecialchars($todayStr) ?>" max="<?= htmlspecialchars($todayStr) ?>" required>
<div class="form-hint">PDF যে তারিখের পত্রিকা — সেই তারিখ দিন। ভবিষ্যতের তারিখ দেওয়া যাবে না।</div>
</div>

<div class="form-field">
<label for="epaper_file">PDF ফাইল <span class="req">*</span></label>
<input type="file" id="epaper_file" name="epaper_file" accept="application/pdf,.pdf" required>
<div class="form-hint">শুধু PDF ফাইল। সর্বোচ্চ সাইজ 50 MB।</div>
</div>
</div>

<label class="checkbox-row">
<input type="checkbox" name="replace_existing" value="1">
<span>এই তারিখের ই-পেপার আগে থেকে থাকলে প্রতিস্থাপন করুন</span>
</label>

<button type="submit" class="btn-submit"><i class="fas fa-upload"></i> ই-পেপার আপলোড করুন</button>
</form>
</div>
</div>

<!-- বিদ্যমান ই-পেপার তালিকা -->
<div class="card">
<div class="card-head">
<h2><i class="fas fa-list"></i> বিদ্যমান ই-পেপার তালিকা</h2>
<?php if ($totalFiles > 0): ?>
<span style="font-size:12px;color:rgba(255,255,255,.6)"><?= epa_toBn((string)$totalFiles) ?> টি ফাইল</span>
<?php endif; ?>
</div>

<?php if (empty($allEpapers)): ?>
<div class="empty-state">
<i class="far fa-folder-open"></i>
<h3>কোনো ই-পেপার নেই</h3>
<p>উপরের ফর্ম থেকে প্রথম ই-পেপার আপলোড করুন।</p>
</div>
<?php else: ?>
<div class="table-wrap">
<table>
<thead>
<tr>
<th>তারিখ</th>
<th>ফাইল নাম</th>
<th>সাইজ</th>
<th>আপলোড হয়েছে</th>
<th style="text-align:right">অ্যাকশন</th>
</tr>
</thead>
<tbody>
<?php foreach ($allEpapers as $date => $info): ?>
<tr>
<td class="date-cell">
<?= htmlspecialchars(epa_toBn(epa_fmtDate($date))) ?>
<?php if ($date === $todayStr): ?><span class="btn-sm btn-today" style="margin-left:6px;cursor:default"><i class="fas fa-star"></i> আজ</span><?php endif; ?>
</td>
<td><i class="far fa-file-pdf" style="color:var(--red)"></i> <?= htmlspecialchars($info['file']) ?></td>
<td class="size-cell"><?= htmlspecialchars($info['size_fmt']) ?></td>
<td class="size-cell"><?= epa_toBn(date('d M Y, h:i A', $info['mtime'])) ?></td>
<td class="action-cell">
<a href="<?= htmlspecialchars($info['path']) ?>" target="_blank" class="btn-sm btn-view"><i class="fas fa-eye"></i> দেখুন</a>
<form method="POST" action="" onsubmit="return confirm('নিশ্চিত? এই ই-পেপার ডিলিট হয়ে যাবে: <?= htmlspecialchars(epa_toBn(epa_fmtDate($date))) ?>')" style="display:inline">
<input type="hidden" name="action" value="delete">
<input type="hidden" name="del_date" value="<?= htmlspecialchars($date) ?>">
<button type="submit" class="btn-sm btn-del"><i class="fas fa-trash"></i> ডিলিট</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>
</div>

</div>

</body>
</html>
