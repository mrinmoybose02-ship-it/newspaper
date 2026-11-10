<?php
/**
 * ══════════════════════════════════════════════════════
 *   ইউজারের পাসওয়ার্ড আপডেট পেজ (update_user_password.php)
 *   - অ্যাডমিন ম্যানুয়ালি ইউজারনেম টাইপ করবে (dropdown নেই)
 *   - নতুন পাসওয়ার্ড দিয়ে আপডেট করবে
 * ══════════════════════════════════════════════════════
 */
require_once 'config.php';

/* ───────────────────────────────────────────────────────
 *  ডাটাবেস প্রস্তুতি (users টেবিলে প্রয়োজনীয় কলাম)
 * ─────────────────────────────────────────────────────── */
try {
    $pdo->exec("ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `is_approved` TINYINT(1) NOT NULL DEFAULT 1");
    $pdo->exec("ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `role` VARCHAR(50) NULL DEFAULT 'viewer'");
} catch (PDOException $e) {
    // কলাম আগে থেকেই থাকলে এ্যারর ইগনোর
}

$msg = ''; $msgType = '';

/* ───────────────────────────────────────────────────────
 *  POST হ্যান্ডেল — পাসওয়ার্ড আপডেট
 * ─────────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_user_password') {
        // ইউজার ম্যানুয়ালি টাইপ করা ইউজারনেম + নতুন পাসওয়ার্ড নেওয়া
        $username     = trim($_POST['username'] ?? '');
        $new_password = $_POST['new_password'] ?? '';

        if (empty($username)) {
            $msg = 'ইউজারনেম লিখুন'; $msgType = 'error';
        } elseif (empty($new_password)) {
            $msg = 'নতুন পাসওয়ার্ড লিখুন'; $msgType = 'error';
        } elseif (strlen($new_password) < 6) {
            $msg = 'পাসওয়ার্ড কমপক্ষে ৬ অক্ষরের হতে হবে'; $msgType = 'error';
        } else {
            // ইউজার আছে কিনা চেক
            $chk = $pdo->prepare("SELECT id, full_name FROM users WHERE username = ?");
            $chk->execute([$username]);
            $user = $chk->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $msg = "'{$username}' ইউজারনেম পাওয়া যায়নি"; $msgType = 'error';
            } else {
                // পাসওয়ার্ড হ্যাশ করে আপডেট
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $upd = $pdo->prepare("UPDATE users SET password = ? WHERE username = ?");
                $upd->execute([$hashed, $username]);

                $msg = "'{$username}' ইউজারের পাসওয়ার্ড সফলভাবে আপডেট হয়েছে";
                $msgType = 'success';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="bn" dir="ltr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ইউজারের পাসওয়ার্ড আপডেট</title>
<link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@300;400;500;600;700&family=Noto+Serif+Bengali:wght@400;600;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{--bg:#F8FAFC;--card:#FFFFFF;--border:#E2E8F0;--red:#B71C1C;--green:#16a34a;--text:#1A1A1A}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Hind Siliguri',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
body::before{content:'';position:fixed;width:500px;height:500px;background:radial-gradient(circle,rgba(183,28,28,.08),transparent 70%);top:-150px;right:-150px;border-radius:50%;pointer-events:none}
body::after{content:'';position:fixed;width:400px;height:400px;background:radial-gradient(circle,rgba(22,163,74,.08),transparent 70%);bottom:-100px;left:-100px;border-radius:50%;pointer-events:none}
.wrapper{width:560px;max-width:100%;position:relative;z-index:2}
.header{text-align:center;margin-bottom:30px}
.header h1{font-family:'Noto Serif Bengali',serif;font-size:28px;color:var(--red);margin-bottom:6px;display:flex;align-items:center;justify-content:center;gap:10px}
.header h1 i{color:var(--green)}
.header p{color:#64748b;font-size:14px}
.card{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:28px;margin-bottom:20px;box-shadow:0 4px 10px rgba(0,0,0,0.05)}
.card h3{font-size:16px;color:var(--red);margin-bottom:16px;padding-bottom:10px;border-bottom:2px solid var(--red);display:flex;align-items:center;gap:8px}
.card h3 i{color:var(--green)}
.form-group{margin-bottom:16px}
.form-group label{display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:6px}
.form-group input[type="text"],
.form-group input[type="password"]{
    width:100%;padding:12px 14px;background:#fff;border:1px solid #cbd5e1;
    border-radius:8px;color:#1e293b;font-family:inherit;font-size:15px;
    outline:none;transition:border-color .3s, box-shadow .3s
}
.form-group input:focus{
    border-color:var(--green);box-shadow:0 0 0 3px rgba(22,163,74,.15)
}
.info-box{background:#FEF3C7;border:1px solid #FCD34D;border-radius:8px;padding:12px 14px;font-size:13px;color:#92400E;margin-bottom:16px;display:flex;gap:8px;align-items:flex-start}
.info-box i{color:#D97706;margin-top:2px}
.btn{display:inline-flex;align-items:center;gap:8px;padding:12px 22px;border:none;border-radius:8px;font-family:inherit;font-size:14px;font-weight:600;cursor:pointer;transition:all .3s}
.btn:hover{transform:translateY(-1px)}
.btn-red{background:var(--red);color:#fff}.btn-red:hover{background:#7F0000}
.btn-green{background:var(--green);color:#fff}.btn-green:hover{background:#15803d}
.btn-block{width:100%;justify-content:center}
.toast{position:fixed;top:20px;right:20px;padding:14px 24px;border-radius:10px;color:#fff;font-weight:600;font-size:14px;z-index:9999;animation:ti .4s ease,to .4s ease 2.6s forwards;box-shadow:0 8px 25px rgba(0,0,0,.2);max-width:90vw}
.toast-success{background:var(--green)}.toast-error{background:var(--red)}
@keyframes ti{from{opacity:0;transform:translateX(60px)}to{opacity:1;transform:translateX(0)}}
@keyframes to{from{opacity:1}to{opacity:0;transform:translateY(-20px)}}
.back-link{text-align:center;margin-top:20px;display:flex;justify-content:center;gap:15px;flex-wrap:wrap}
.back-link a{color:var(--red);font-size:14px;text-decoration:none;transition:color .2s}
.back-link a:hover{color:var(--green)}
@media(max-width:600px){.header h1{font-size:22px}}
</style>
</head>
<body>

<?php if ($msg): ?>
<div class="toast toast-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="wrapper">
    <div class="header">
        <h1><i class="fas fa-user-pen"></i> ইউজারের পাসওয়ার্ড আপডেট</h1>
        <p>ইউজারনেম ম্যানুয়ালি লিখুন ও নতুন পাসওয়ার্ড দিন</p>
    </div>

    <!-- পাসওয়ার্ড আপডেট ফর্ম -->
    <div class="card">
        <h3><i class="fas fa-key"></i> পাসওয়ার্ড আপডেট করুন</h3>

        <div class="info-box">
            <i class="fas fa-info-circle"></i>
            <span>ইউজারনেম ম্যানুয়ালি টাইপ করুন (dropdown নেই)। তারপর নতুন পাসওয়ার্ড দিয়ে "পাসওয়ার্ড আপডেট করুন" বাটনে ক্লিক করুন।</span>
        </div>

        <form method="POST" action="" id="pwdForm" autocomplete="off">
            <input type="hidden" name="action" value="update_user_password">

            <!-- ✅ ইউজারনেম — ম্যানুয়াল টাইপিং (dropdown সরানো হয়েছে) -->
            <div class="form-group">
                <label><i class="fas fa-user"></i> ইউজারনেম (Username)</label>
                <input type="text" name="username" id="username" required
                       placeholder="ইউজারনেম লিখুন"
                       autocomplete="off" autocapitalize="off" spellcheck="false">
            </div>

            <!-- নতুন পাসওয়ার্ড -->
            <div class="form-group">
                <label><i class="fas fa-lock"></i> নতুন পাসওয়ার্ড</label>
                <input type="password" name="new_password" id="new_password" required
                       placeholder="নতুন পাসওয়ার্ড লিখুন"
                       autocomplete="new-password" minlength="6">
            </div>

            <button type="submit" class="btn btn-green btn-block" id="submitBtn">
                <i class="fas fa-lock-open"></i> পাসওয়ার্ড আপডেট করুন
            </button>
        </form>
    </div>

    <div class="back-link">
        <a href="index.php?page=admin_dashboard"><i class="fas fa-arrow-left"></i> অ্যাডমিন ড্যাশবোর্ডে ফিরে যান</a>
    </div>
</div>

<script>
// টোস্ট অটো-হাইড
setTimeout(function(){
    document.querySelectorAll('.toast').forEach(function(t){
        if(t.parentNode) t.parentNode.removeChild(t);
    });
}, 3200);

// সাবমিট বাটনে লোডিং স্টেট
document.getElementById('pwdForm').addEventListener('submit', function(){
    var btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> আপডেট হচ্ছে...';
});
</script>
</body>
</html>
