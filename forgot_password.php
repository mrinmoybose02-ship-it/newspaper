<?php
/**
 * ══════════════════════════════════════════════════════
 *   পাসওয়ার্ড রিসেট ও ইউজার ম্যানেজমেন্ট টুল
 * ══════════════════════════════════════════════════════
 */
require_once 'config.php';

// Ensure necessary columns exist in the database
try {
    $pdo->exec("ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `is_approved` TINYINT(1) NOT NULL DEFAULT 1");
    $pdo->exec("ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `permissions` TEXT NULL DEFAULT '{}'");
    $pdo->exec("ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `role` VARCHAR(50) NULL DEFAULT 'viewer'");
} catch (PDOException $e) {
    // Ignore if they already exist
}

 $msg = ''; $msgType = '';

// Fetch all users for the dropdown
 $allUsers = [];
try {
    $allUsers = $pdo->query("SELECT * FROM users ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table doesn't exist yet
}

/* POST হ্যান্ডেল */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Update Existing User Password
    if ($action === 'update_password') {
        $targetUserId = (int)($_POST['target_user_id'] ?? 0);
        $newPass = trim($_POST['new_password'] ?? '');
        
        if (empty($targetUserId) || empty($newPass)) { 
            $msg = 'ইউজার এবং নতুন পাসওয়ার্ড দিন।'; $msgType = 'error'; 
        } else {
            $hash = password_hash($newPass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hash, $targetUserId]);
            
            if ($stmt->rowCount() > 0) {
                $msg = "পাসওয়ার্ড সফলভাবে আপডেট হয়েছে!"; $msgType = 'success';
            } else {
                $msg = "পাসওয়ার্ড আপডেট করতে সমস্যা হয়েছে বা একই পাসওয়ার্ড দেওয়া হয়েছে।"; $msgType = 'error';
            }
        }
    }

    // 2. Create New User with Roles & Permissions
    if ($action === 'create_user') {
        $username = trim($_POST['new_username'] ?? '');
        $rawPass  = trim($_POST['new_user_pass'] ?? '');
        $fullName = trim($_POST['new_full_name'] ?? '') ?: 'অ্যাডমিন';
        
        if (empty($username) || empty($rawPass)) { 
            $msg = 'সব ফিল্ড পূরণ করুন'; $msgType = 'error'; 
        } else {
            $chk = $pdo->prepare("SELECT id FROM users WHERE username = ?"); 
            $chk->execute([$username]);
            
            if ($chk->fetch()) { 
                $msg = "'{$username}' ইতোমধ্যে আছে"; $msgType = 'error'; 
            } else {
                // --- YOUR REQUESTED CODE STARTS HERE ---
                
                // Define role permissions map
                $rolePermsMap = [
                    'editor'         => ['dashboard','add_news','manage_news','password'],
                    'publisher'      => ['dashboard','add_news','manage_news','breaking','categories','home_panels','password'],
                    'ad_manager'     => ['dashboard','ads','password'],
                    'social_manager' => ['dashboard','social','contact','videos','password'],
                    'market_viewer'  => ['dashboard','nse_market','password'],
                    'viewer'         => ['dashboard','password'],
                ];

                // Get selected role from form
                $selectedRole = $_POST['role'] ?? 'viewer';

                // Get permissions for the selected role
                $perms = $rolePermsMap[$selectedRole] ?? ['dashboard','password'];
                $permJson = json_encode($perms);

                // Security: Hash the plain password before saving
                $password = password_hash($rawPass, PASSWORD_DEFAULT);

                // ====== FIX: Save BOTH permissions (as JSON, NOT NULL) AND role name ======
                $pdo->prepare("INSERT INTO users (username, password, full_name, is_approved, permissions, role) VALUES (?, ?, ?, 0, ?, ?)")->execute([
                    $username, 
                    $password, 
                    $fullName, 
                    $permJson,        // JSON array — NOT NULL
                    $selectedRole     // Role name stored in database
                ]);

                // --- YOUR REQUESTED CODE ENDS HERE ---

                $msg = "নতুন ইউজার '{$username}' তৈরি হয়েছে! অনুমোদনের অপেক্ষায়।"; $msgType = 'success';
                
                // Refresh user list
                $allUsers = $pdo->query("SELECT * FROM users ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
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
<title>পাসওয়ার্ড রিসেট ও ইউজার ম্যানেজমেন্ট</title>
<link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@300;400;500;600;700&family=Noto+Serif+Bengali:wght@400;600;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{--bg:#F8FAFC;--card:#FFFFFF;--border:#E2E8F0;--red:#B71C1C;--green:#16a34a;--text:#1A1A1A}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Hind Siliguri',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
body::before{content:'';position:fixed;width:500px;height:500px;background:radial-gradient(circle,rgba(183,28,28,.08),transparent 70%);top:-150px;right:-150px;border-radius:50%;pointer-events:none}
body::after{content:'';position:fixed;width:400px;height:400px;background:radial-gradient(circle,rgba(22,163,74,.08),transparent 70%);bottom:-100px;left:-100px;border-radius:50%;pointer-events:none}
.wrapper{width:600px;max-width:100%;position:relative;z-index:2}
.header{text-align:center;margin-bottom:30px}
.header h1{font-family:'Noto Serif Bengali',serif;font-size:28px;color:var(--red);margin-bottom:6px}
.header p{color:#64748b;font-size:14px}
.card{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:28px;margin-bottom:20px;box-shadow:0 4px 10px rgba(0,0,0,0.05)}
.card h3{font-size:16px;color:var(--red);margin-bottom:16px;padding-bottom:10px;border-bottom:2px solid var(--red);display:flex;align-items:center;gap:8px}
.card h3 i{color:var(--green)}
.form-group{margin-bottom:16px}
.form-group label{display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:6px}
.form-group input[type="text"],.form-group input[type="password"],.form-group select{width:100%;padding:10px 14px;background:#fff;border:1px solid #cbd5e1;border-radius:8px;color:#1e293b;font-family:inherit;font-size:14px;outline:none;transition:border-color .3s, box-shadow .3s}
.form-group input:focus,.form-group select:focus{border-color:var(--green);box-shadow:0 0 0 3px rgba(22,163,74,.15)}
.form-group select option{background:#fff;color:#1e293b}
.btn{display:inline-flex;align-items:center;gap:8px;padding:10px 22px;border:none;border-radius:8px;font-family:inherit;font-size:14px;font-weight:600;cursor:pointer;transition:all .3s}
.btn:hover{transform:translateY(-1px)}
.btn-red{background:var(--red);color:#fff}.btn-red:hover{background:#7F0000}
.btn-green{background:var(--green);color:#fff}.btn-green:hover{background:#15803d}
.btn-block{width:100%;justify-content:center}
.toast{position:fixed;top:20px;right:20px;padding:14px 24px;border-radius:10px;color:#fff;font-weight:600;font-size:14px;z-index:9999;animation:ti .4s ease,to .4s ease 2.6s forwards;box-shadow:0 8px 25px rgba(0,0,0,.2);max-width:90vw}
.toast-success{background:var(--green)}.toast-error{background:var(--red)}
@keyframes ti{from{opacity:0;transform:translateX(60px)}to{opacity:1;transform:translateX(0)}}
@keyframes to{from{opacity:1}to{opacity:0;transform:translateY(-20px)}}
.back-link{text-align:center;margin-top:20px}
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
        <h1><i class="fas fa-key" style="color:var(--green)"></i> পাসওয়ার্ড রিসেট</h1>
        <p>নিরাপদ পাসওয়ার্ড তৈরি ও ডাটাবেসে আপডেট করুন</p>
    </div>

    <!-- ১. বর্তমান ইউজারের পাসওয়ার্ড রিসেট -->
    <?php if (!empty($allUsers)): ?>
    <div class="card">
        <h3><i class="fas fa-user-pen"></i> ইউজারের পাসওয়ার্ড আপডেট</h3>
        <form method="POST" action="">
            <input type="hidden" name="action" value="update_password">
            <div class="form-group">
                <label>ইউজার বেছে নিন</label>
                <select name="target_user_id" required>
                    <option value="">-- ইউজার বেছে নিন --</option>
                    <?php foreach ($allUsers as $u): ?>
                    <option value="<?= htmlspecialchars($u['id']) ?>">
                        <?= htmlspecialchars($u['username']) ?> (<?= htmlspecialchars($u['full_name'] ?? 'N/A') ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>নতুন পাসওয়ার্ড</label>
                <input type="text" name="new_password" required placeholder="নতুন পাসওয়ার্ড লিখুন" autocomplete="off">
            </div>
            <button type="submit" class="btn btn-green btn-block"><i class="fas fa-save"></i> পাসওয়ার্ড আপডেট করুন</button>
        </form>
    </div>
    <?php endif; ?>

    <!-- ২. নতুন ইউজার তৈরি -->
    <div class="card">
        <h3><i class="fas fa-user-plus"></i> নতুন ইউজার তৈরি</h3>
        <form method="POST" action="">
            <input type="hidden" name="action" value="create_user">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div class="form-group">
                    <label>ইউজারনেম</label>
                    <input type="text" name="new_username" required placeholder="new_user" autocomplete="off">
                </div>
                <div class="form-group">
                    <label>পুরো নাম</label>
                    <input type="text" name="new_full_name" placeholder="সহকারী অ্যাডমিন" autocomplete="off">
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div class="form-group">
                    <label>পাসওয়ার্ড</label>
                    <input type="text" name="new_user_pass" required placeholder="পাসওয়ার্ড লিখুন" autocomplete="off">
                </div>
                <div class="form-group">
                    <label>ইউজার রোল (Role)</label>
                    <select name="role" required>
                        <option value="viewer">Viewer (ভিউয়ার)</option>
                        <option value="editor">Editor (এডিটর)</option>
                        <option value="publisher">Publisher (পাবলিশার)</option>
                        <option value="ad_manager">Ad Manager (অ্যাড ম্যানেজার)</option>
                        <option value="social_manager">Social Manager (সোশ্যাল ম্যানেজার)</option>
                        <option value="market_viewer">Market Viewer (মার্কেট ভিউয়ার)</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn btn-red btn-block"><i class="fas fa-user-plus"></i> ইউজার তৈরি করুন</button>
        </form>
    </div>

    <div class="back-link">
        <a href="index.php?page=admin_login"><i class="fas fa-arrow-left"></i> লগইন পেজে ফিরে যান</a>
    </div>
</div>

<script>
setTimeout(function(){document.querySelectorAll('.toast').forEach(function(t){if(t.parentNode)t.parentNode.removeChild(t)})},3200);
</script>
</body>
</html>