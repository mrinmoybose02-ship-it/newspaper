<?php
// Prevent "session already active" notice
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: ?page=admin_dashboard');
    exit;
}

// Create password_reset_requests table if it doesn't exist (Prevents crashes)
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

 $loginError = '';
 $loginAlert = ''; // Variable to hold the JavaScript popup message

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $default_user = 'admin';
    $default_pass = 'admin123';
    $login_success = false;

    // 1. CHECK IF USER HAS A PENDING RESET REQUEST
    try {
        $chkReq = $pdo->prepare("SELECT id FROM password_reset_requests WHERE username = ? AND status = 'pending' LIMIT 1");
        $chkReq->execute([$username]);
        if ($chkReq->fetch()) {
            $loginError = 'আপনার পাসওয়ার্ড রিসেট অনুরোধ এখনও অনুমোদিত হয়নি (Not Approved)। অ্যাডমিন অনুমোদন করা পর্যন্ত আপনি লগইন করতে পারবেন না।';
            $loginAlert = $loginError; // Set popup alert
        }
    } catch (PDOException $e) {}

    // 2. PROCEED TO LOGIN ONLY IF NO PENDING REQUEST
    if (empty($loginError)) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                if (password_verify($password, $user['password']) || $user['password'] === $password) {
                    // Check if user is approved
                    if (isset($user['is_approved']) && $user['is_approved'] == 0) {
                        $loginError = 'আপনার অ্যাকাউন্ট এখনও অ্যাডমিন কর্তৃক অনুমোদিত হয়নি (Not Approved)। অনুমোদনের পর লগইন করুন।';
                        $loginAlert = $loginError;
                    } else {
                        $login_success = true;
                        $_SESSION['user_id'] = $user['id'];
                    }
                }
            }
        } catch (PDOException $e) {
            if ($username === $default_user && $password === $default_pass) {
                $login_success = true;
                $_SESSION['user_id'] = 1;
            }
        }

        if (!$login_success && $username === $default_user && $password === $default_pass) {
            $login_success = true;
            $_SESSION['user_id'] = 1;
        }

        if ($login_success) {
            header('Location: ?page=admin_dashboard');
            exit;
        } else {
            if (empty($loginError)) {
                $loginError = 'ভুল ইউজারনেম বা পাসওয়ার্ড। (ডিফল্ট: admin / admin123)';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - সংবাদ সংকলন</title>
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;600;700&family=Noto+Serif+Bengali:wght@700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { margin: 0; padding: 0; font-family: 'Hind Siliguri', sans-serif; background: #F4F1EB; display: flex; justify-content: center; align-items: center; height: 100vh; }
        .login-box { background: #fff; padding: 40px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); width: 100%; max-width: 400px; border-top: 5px solid #B71C1C; }
        .login-box h2 { font-family: 'Noto Serif Bengali', serif; color: #B71C1C; text-align: center; margin-bottom: 30px; font-size: 24px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #333; }
        .form-group input { width: 100%; padding: 12px; border: 1px solid #DDD5C8; border-radius: 4px; font-size: 16px; box-sizing: border-box; font-family: 'Hind Siliguri', sans-serif; }
        .form-group input:focus { border-color: #B71C1C; outline: none; }
        .btn-login { width: 100%; padding: 12px; background: #B71C1C; color: #fff; border: none; border-radius: 4px; font-size: 16px; font-weight: 700; cursor: pointer; transition: background 0.2s; font-family: 'Hind Siliguri', sans-serif; }
        .btn-login:hover { background: #7F0000; }
        .error-msg { background: #ffebee; color: #c62828; padding: 10px; border-radius: 4px; margin-bottom: 20px; text-align: center; font-size: 14px; border: 1px solid #ef9a9a; }
        .back-link { display: block; text-align: center; margin-top: 20px; color: #666; text-decoration: none; font-size: 14px; }
        .back-link:hover { color: #B71C1C; }
        
        /* Action Links Container */
        .action-links { display: flex; justify-content: space-between; margin-top: 15px; margin-bottom: 15px; gap: 10px; }
        .action-links a { text-align: center; color: #B71C1C; text-decoration: none; font-size: 14px; font-weight: 600; background: #F4F1EB; padding: 10px; border-radius: 5px; flex: 1; border: 1px solid #DDD5C8; transition: 0.3s; }
        .action-links a:hover { background: #B71C1C; color: #fff; border-color: #B71C1C; }
    </style>
</head>
<body>
    <div class="login-box">
        <h2><i class="fas fa-user-shield"></i> অ্যাডমিন লগইন</h2>
        <?php if (!empty($loginError)): ?>
            <div class="error-msg"><?= htmlspecialchars($loginError) ?></div>
        <?php endif; ?>
        <form method="POST" action="?page=admin_login">
            <div class="form-group">
                <label for="username">ইউজারনেম</label>
                <input type="text" id="username" name="username" required autocomplete="off">
            </div>
            <div class="form-group">
                <label for="password">পাসওয়ার্ড</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn-login">লগইন করুন</button>
        </form>
        
        <!-- নতুন লিংক সেকশন: পাসওয়ার্ড রিসেট এবং নতুন ইউজার তৈরি -->
        <div class="action-links">
            <a href="update_password.php"><i class="fas fa-key"></i> পাসওয়ার্ড রিসেট</a>
            <a href="create_user.php"><i class="fas fa-user-plus"></i> নতুন ইউজার</a>
        </div>

        <a href="?page=home" class="back-link"><i class="fas fa-arrow-left"></i> ওয়েবসাইটে ফিরুন</a>
    </div>

    <?php if (!empty($loginAlert)): ?>
    <script>
        // Using json_encode is the safest way to pass PHP strings to JavaScript
        alert(<?= json_encode($loginAlert) ?>);
    </script>
    <?php endif; ?>
</body>
</html>