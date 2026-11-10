<?php
/**
 * ══════════════════════════════════════════════════════
 *   অ্যাডমিন লগইন পেজ  (admin_login.php)
 * ══════════════════════════════════════════════════════
 * admin_dashboard.php-এর পাসওয়ার্ড চেক পদ্ধতির সাথে মিলিয়ে বানানো হয়েছে।
 */

// Start session only if not already active (config.php may have started it)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';

// Make sure PDO reports errors as exceptions so failures are visible
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// যদি ইতিমধ্যে লগইন থাকেন, ড্যাশবোর্ডে পাঠাই
if (!empty($_SESSION['user_id'])) {
    header('Location: index.php?page=admin_dashboard');
    exit;
}

// লগআউট হ্যান্ডেল (admin_dashboard-এর logout লিংক এখানে আসে)
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    header('Location: index.php?page=admin_login');
    exit;
}

// এরর মেসেজ অনুবাদ (admin_dashboard থেকে আসা ?error= প্যারামিটার হ্যান্ডেল)
$prefillError = '';
if (isset($_GET['error'])) {
    $errMap = [
        'not_approved' => 'আপনার অ্যাকাউন্ট এখনো অনুমোদিত হয়নি। অ্যাডমিনের অপেক্ষায়।',
        'db_error'     => 'ডাটাবেস ত্রুটি হয়েছে। কিছুক্ষণ পর আবার চেষ্টা করুন।',
    ];
    $prefillError = $errMap[$_GET['error']] ?? '';
}

$msg = ''; $msgType = '';

/* POST হ্যান্ডেল – লগইন যাচাই */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $msg = 'সব ফিল্ড পূরণ করুন'; $msgType = 'error';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // ★ পাসওয়ার্ড চেক – প্লেইন টেক্সট (admin_dashboard-এর সাথে মিল)
            // প্রথমে সরাসরি তুলনা (প্লেইন টেক্সট পাসওয়ার্ডের জন্য), পারলে password_verify (হ্যাশড পাসওয়ার্ডের জন্য)
            $passwordOK = false;
            if ($user && !empty($user['password'])) {
                if ($password === $user['password']) {
                    $passwordOK = true;
                } elseif (password_verify($password, $user['password'])) {
                    $passwordOK = true;
                }
            }

            if (!$user || !$passwordOK) {
                $msg = 'ভুল ইউজারনেম বা পাসওয়ার্ড'; $msgType = 'error';
            } elseif (isset($user['is_approved']) && (int)$user['is_approved'] !== 1) {
                $msg = 'আপনার অ্যাকাউন্ট এখনো অনুমোদিত হয়নি। অ্যাডমিনের অপেক্ষায়।';
                $msgType = 'error';
            } else {
                // ★ সফল লগইন – সেশনে ইউজার তথ্য রাখি (admin_dashboard যা যা চায়)
                $_SESSION['user_id']      = $user['id'];
                $_SESSION['username']     = $user['username'];
                $_SESSION['full_name']    = $user['full_name'] ?? $user['username'];

                // CSRF টোকেন তৈরি (admin_dashboard verifyCSRF() এর জন্য দরকার)
                if (empty($_SESSION['csrf_token'])) {
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                }

                // রোল ও পারমিশন
                $_SESSION['role'] = $user['role'] ?? 'viewer';
                $rawPerms = $user['permissions'] ?? null;
                $_SESSION['permissions'] = ($rawPerms === null)
                    ? null   // super admin (পুরোনো ইউজারদের permissions NULL থাকলে super admin)
                    : (json_decode($rawPerms, true) ?: []);

                // ড্যাশবোর্ডে রিডাইরেক্ট
                header('Location: index.php?page=admin_dashboard');
                exit;
            }
        } catch (PDOException $e) {
            $msg = 'ডাটাবেস ত্রুটি: ' . htmlspecialchars($e->getMessage());
            $msgType = 'error';
        }
    }
}

// ড্যাশবোর্ড থেকে আসা এরর আর লগইন ফর্মের এরর একত্র করি
if ($prefillError && !$msg) {
    $msg = $prefillError;
    $msgType = 'error';
}
?>
<!DOCTYPE html>
<html lang="bn" dir="ltr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>অ্যাডমিন লগইন</title>
<link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@300;400;500;600;700&family=Noto+Serif+Bengali:wght@400;600;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{--bg:#F8FAFC;--card:#FFFFFF;--border:#E2E8F0;--red:#B71C1C;--green:#16a34a;--text:#1A1A1A}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Hind Siliguri',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
body::before{content:'';position:fixed;width:500px;height:500px;background:radial-gradient(circle,rgba(183,28,28,.08),transparent 70%);top:-150px;right:-150px;border-radius:50%;pointer-events:none}
body::after{content:'';position:fixed;width:400px;height:400px;background:radial-gradient(circle,rgba(22,163,74,.08),transparent 70%);bottom:-100px;left:-100px;border-radius:50%;pointer-events:none}
.wrapper{width:420px;max-width:100%;position:relative;z-index:2}
.header{text-align:center;margin-bottom:30px}
.header h1{font-family:'Noto Serif Bengali',serif;font-size:28px;color:var(--red);margin-bottom:6px}
.header p{color:#64748b;font-size:14px}
.card{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:28px;margin-bottom:20px;box-shadow:0 4px 10px rgba(0,0,0,0.05)}
.card h3{font-size:16px;color:var(--red);margin-bottom:16px;padding-bottom:10px;border-bottom:2px solid var(--red);display:flex;align-items:center;gap:8px}
.card h3 i{color:var(--green)}
.form-group{margin-bottom:16px}
.form-group label{display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:6px}
.form-group input[type="text"],.form-group input[type="password"]{width:100%;padding:10px 14px;background:#fff;border:1px solid #cbd5e1;border-radius:8px;color:#1e293b;font-family:inherit;font-size:14px;outline:none;transition:border-color .3s, box-shadow .3s}
.form-group input:focus{border-color:var(--green);box-shadow:0 0 0 3px rgba(22,163,74,.15)}
.btn{display:inline-flex;align-items:center;gap:8px;padding:10px 22px;border:none;border-radius:8px;font-family:inherit;font-size:14px;font-weight:600;cursor:pointer;transition:all .3s}
.btn:hover{transform:translateY(-1px)}
.btn-red{background:var(--red);color:#fff}.btn-red:hover{background:#7F0000}
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
        <h1><i class="fas fa-lock" style="color:var(--green)"></i> অ্যাডমিন লগইন</h1>
        <p>লগইন করতে ইউজারনেম ও পাসওয়ার্ড দিন</p>
    </div>

    <div class="card">
        <h3><i class="fas fa-sign-in-alt"></i> লগইন</h3>
        <form method="POST" action="">
            <div class="form-group">
                <label>ইউজারনেম</label>
                <input type="text" name="username" required placeholder="ইউজারনেম" autocomplete="username" autofocus value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>পাসওয়ার্ড</label>
                <input type="password" name="password" required placeholder="পাসওয়ার্ড" autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-red btn-block"><i class="fas fa-sign-in-alt"></i> লগইন করুন</button>
        </form>
    </div>

    <div class="back-link">
        <a href="create_user.php"><i class="fas fa-user-plus"></i> নতুন ইউজার তৈরি</a>
        <a href="update_password.php"><i class="fas fa-key"></i> পাসওয়ার্ড রিসেট</a>
    </div>
</div>

<script>
setTimeout(function(){document.querySelectorAll('.toast').forEach(function(t){if(t.parentNode)t.parentNode.removeChild(t)})},3200);
</script>
</body>
</html>
