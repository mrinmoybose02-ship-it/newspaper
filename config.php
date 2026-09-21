<?php
/**
 * ══════════════════════════════════════════════════════════════
 *   config.php — সংবাদ সংকলন ডাটাবেস কনফিগারেশন ও হেল্পার ফাংশন
 * ══════════════════════════════════════════════════════════════
 */

session_start();

/* ── ডাটাবেস কনফিগারেশন ──
 * XAMPP ডিফল্ট: ইউজার root, পাস খালি
 * আপনার XAMPP-এ পাসওয়ার্ড সেট করা থাকলে নিচে লিখুন
 */
 $db_host = 'localhost';
 $db_name = 'songbad_songolon';   // ডাটাবেসের নাম (অটো-তৈরি হবে)
 $db_user = 'root';
 $db_pass = '';                    // XAMPP ডিফল্টে খালি, পাস থাকলে এখানে দিন

/* ── ডাটাবেস অটো-তৈরি (ডাটাবেস না থাকলে নিজেই বানিয়ে নেবে) ── */
try {
    $pdo_tmp = new PDO(
        "mysql:host={$db_host};charset=utf8mb4",
        $db_user,
        $db_pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $pdo_tmp->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo_tmp = null;
} catch (PDOException $e) {
    die("<div style='background:#fff3f3;color:#991b1b;padding:20px;border-radius:8px;border-left:4px solid #dc2626;font-family:sans-serif;max-width:500px;margin:40px auto;'>
        <strong>ডাটাবেস কানেকশন ব্যর্থ</strong><br><br>
        <code style='background:#fecaca;padding:2px 6px;border-radius:4px;font-size:13px;'>{$e->getMessage()}</code><br><br>
        <strong>সমাধান:</strong><br>
        আপনার XAMPP-এ MySQL পাসওয়ার্ড সেট করা আছে কিনা চেক করুন।<br>
        এই ফাইলের <code>\$db_pass</code> এ সঠিক পাসওয়ার্ড দিন।<br>
        XAMPP ডিফল্টে পাসওয়ার্ড খালি থাকে।
    </div>");
}

/* ── ডাটাবেস কানেকশন ── */
try {
    $pdo = new PDO(
        "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false
        ]
    );
} catch (PDOException $e) {
    die("ডাটাবেস কানেকশন ব্যর্থ: " . $e->getMessage());
}

/* ── কনস্ট্যান্ট ── */
if (!defined('NEWS_PER_PAGE')) {
    define('NEWS_PER_PAGE', 12);
}
if (!defined('ADMIN_PAGES')) {
    define('ADMIN_PAGES', ['admin_dashboard', 'admin_add', 'admin_edit', 'admin_manage', 'admin_breaking', 'admin_categories', 'admin_cat_edit', 'admin_password']);
}

/* ══════════════════════════════════════════════════════════
   বাংলা সংখ্যা কনভার্টার
   ══════════════════════════════════════════════════════════ */
if (!function_exists('toBn')) {
    function toBn($num) {
        $bn = ['০','১','২','৩','৪','৫','৬','৭','৮','৯'];
        return str_replace(['0','1','2','3','4','5','6','7','8','9'], $bn, (string)$num);
    }
}

/* ══════════════════════════════════════════════════════════
   স্লাগ জেনারেটর
   ══════════════════════════════════════════════════════════ */
if (!function_exists('generateSlug')) {
    function generateSlug($text) {
        $text = preg_replace('/[^\p{Bengali}\p{Latin}0-9\s-]/u', '', $text);
        $text = preg_replace('/[\s_]+/', '-', trim($text));
        $text = preg_replace('/-+/', '-', $text);
        return trim(strtolower($text), '-');
    }
}

/* ══════════════════════════════════════════════════════════
   নিউজ কী ম্যাপার
   ══════════════════════════════════════════════════════════ */
if (!function_exists('mapNewsKeys')) {
    function mapNewsKeys($row) {
        return [
            'id'         => $row['id'] ?? null,
            'title'      => $row['title'] ?? '',
            'slug'       => $row['slug'] ?? '',
            'content'    => $row['content'] ?? '',
            'image'      => $row['image'] ?? '',
            'tags'       => $row['tags'] ?? '',
            'category_id'=> $row['category_id'] ?? null,
            'category'   => $row['category_name'] ?? ($row['category'] ?? ''),
            'author'     => $row['author'] ?? 'অজানা',
            'status'     => $row['status'] ?? 'draft',
            'featured'   => $row['is_featured'] ?? ($row['featured'] ?? 0),
            'created_at' => $row['created_at'] ?? '',
            'updated_at' => $row['updated_at'] ?? '',
        ];
    }
}

/* ══════════════════════════════════════════════════════════
   SEO বিল্ডার
   ══════════════════════════════════════════════════════════ */
if (!function_exists('buildSEO')) {
    function buildSEO($page, $singleNews, $currentCat, $searchQuery) {
        $siteName = 'সংবাদ সংকলন';
        $defaults = [
            'title'       => $siteName . ' — পশ্চিমবঙ্গের বিশ্বস্ত সংবাদমাধ্যম',
            'description' => 'পশ্চিমবঙ্গ ও বিশ্বের সর্বশেষ সংবাদ, বিশ্লেষণ ও মতামত।',
            'robots'      => 'index, follow',
            'image'       => 'https://picsum.photos/seed/og/800/450'
        ];
        if ($page === 'single' && $singleNews) {
            $defaults['title'] = $singleNews['title'] . ' — ' . $siteName;
            $defaults['description'] = mb_substr(strip_tags($singleNews['content']), 0, 160);
            $defaults['image'] = $singleNews['image'] ?: $defaults['image'];
        } elseif ($currentCat) {
            $defaults['title'] = $currentCat . ' — ' . $siteName;
            $defaults['description'] = $currentCat . ' বিভাগের সর্বশেষ সংবাদ পড়ুন ' . $siteName . ' থেকে।';
        } elseif ($searchQuery) {
            $defaults['title'] = '"' . $searchQuery . '" এর ফলাফল — ' . $siteName;
            $defaults['description'] = '"' . $searchQuery . '" সম্পর্কিত সংবাদ খুঁজুন।';
        } elseif (in_array($page, ADMIN_PAGES)) {
            $defaults['robots'] = 'noindex, nofollow';
        }
        return $defaults;
    }
}

/* ══════════════════════════════════════════════════════════
   ক্যানোনিক্যাল URL
   ══════════════════════════════════════════════════════════ */
if (!function_exists('getCanonicalURL')) {
    function getCanonicalURL() {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $protocol . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '/');
    }
}

/* ══════════════════════════════════════════════════════════
   প্যাজিনেশন রেন্ডারার
   ══════════════════════════════════════════════════════════ */
if (!function_exists('renderPagination')) {
    function renderPagination($current, $total, $base) {
        if ($total <= 1) return '';
        $html = '';
        if ($current > 1) {
            $html .= '<a href="' . $base . '&p=' . ($current - 1) . '"><i class="fas fa-chevron-left"></i></a>';
        }
        $start = max(1, $current - 2);
        $end = min($total, $current + 2);
        if ($start > 1) {
            $html .= '<a href="' . $base . '&p=1">১</a>';
            if ($start > 2) $html .= '<span class="pg-dots">...</span>';
        }
        for ($i = $start; $i <= $end; $i++) {
            $html .= ($i === $current)
                ? '<span class="pg-active">' . toBn($i) . '</span>'
                : '<a href="' . $base . '&p=' . $i . '">' . toBn($i) . '</a>';
        }
        if ($end < $total) {
            if ($end < $total - 1) $html .= '<span class="pg-dots">...</span>';
            $html .= '<a href="' . $base . '&p=' . $total . '">' . toBn($total) . '</a>';
        }
        if ($current < $total) {
            $html .= '<a href="' . $base . '&p=' . ($current + 1) . '"><i class="fas fa-chevron-right"></i></a>';
        }
        return $html;
    }
}

/* ══════════════════════════════════════════════════════════
   CSRF প্রটেকশন
   ══════════════════════════════════════════════════════════ */
if (!function_exists('getCSRFToken')) {
    function getCSRFToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('verifyCSRF')) {
    function verifyCSRF() {
        $token = $_POST['csrf_token'] ?? '';
        return hash_equals($_SESSION['csrf_token'] ?? '', $token);
    }
}

if (!function_exists('csrfField')) {
    function csrfField() {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(getCSRFToken()) . '">';
    }
}

/* ══════════════════════════════════════════════════════════
   রেট লিমিট
   ══════════════════════════════════════════════════════════ */
if (!function_exists('checkRateLimit')) {
    function checkRateLimit() {
        $key = 'login_attempts_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $data = $_SESSION[$key] ?? ['count' => 0, 'last' => 0];
        if ($data['count'] >= 5 && (time() - $data['last']) < 300) {
            return 300 - (time() - $data['last']);
        }
        return true;
    }
}

if (!function_exists('recordFailedLogin')) {
    function recordFailedLogin() {
        $key = 'login_attempts_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $data = $_SESSION[$key] ?? ['count' => 0, 'last' => 0];
        if ((time() - $data['last']) > 300) {
            $data = ['count' => 0, 'last' => 0];
        }
        $data['count']++;
        $data['last'] = time();
        $_SESSION[$key] = $data;
    }
}

if (!function_exists('resetLoginAttempts')) {
    function resetLoginAttempts() {
        unset($_SESSION['login_attempts_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown')]);
    }
}

/* ══════════════════════════════════════════════════════════
   ইমেজ আপলোড হ্যান্ডলার
   ══════════════════════════════════════════════════════════ */
if (!function_exists('handleUpload')) {
    function handleUpload($field) {
        if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
            return ['error' => 'আপলোড ব্যর্থ (কোড: ' . $_FILES[$field]['error'] . ')'];
        }
        $maxSize = 2 * 1024 * 1024;
        if ($_FILES[$field]['size'] > $maxSize) {
            return ['error' => 'ফাইলের আকার ২MB এর বেশি'];
        }
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $_FILES[$field]['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, $allowed)) {
            return ['error' => 'শুধু JPG, PNG, WebP, GIF ফাইল আপলোড করুন'];
        }
        $ext = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif'
        ][$mime];
        $name = 'news_' . time() . '_' . random_int(1000, 9999) . '.' . $ext;
        $dir = 'uploads/news/';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $path = $dir . $name;
        if (!move_uploaded_file($_FILES[$field]['tmp_name'], $path)) {
            return ['error' => 'ফাইল সেভ করা যায়নি'];
        }
        return ['path' => $path];
    }
}

/* ══════════════════════════════════════════════════════════
   ইউনিক স্লাগ হেল্পার (সংবাদ)
   ══════════════════════════════════════════════════════════ */
if (!function_exists('makeUniqueSlug')) {
    function makeUniqueSlug($pdo, $slug, $excludeId = null) {
        if (empty($slug)) $slug = 'news-' . time() . '-' . rand(100, 9999);
        $baseSlug = $slug;
        $counter = 1;
        while (true) {
            if ($excludeId !== null) {
                $stmt = $pdo->prepare("SELECT id FROM news WHERE slug = ? AND id != ?");
                $stmt->execute([$slug, $excludeId]);
            } else {
                $stmt = $pdo->prepare("SELECT id FROM news WHERE slug = ?");
                $stmt->execute([$slug]);
            }
            if (!$stmt->fetch()) break;
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }
        return $slug;
    }
}

/* ══════════════════════════════════════════════════════════
   ইউনিক স্লাগ হেল্পার (ক্যাটেগরি)
   ══════════════════════════════════════════════════════════ */
if (!function_exists('makeUniqueCatSlug')) {
    function makeUniqueCatSlug($pdo, $slug, $excludeId = null) {
        if (empty($slug)) $slug = 'cat-' . time() . '-' . rand(100, 9999);
        $baseSlug = $slug;
        $counter = 1;
        while (true) {
            if ($excludeId !== null) {
                $stmt = $pdo->prepare("SELECT id FROM categories WHERE slug = ? AND id != ?");
                $stmt->execute([$slug, $excludeId]);
            } else {
                $stmt = $pdo->prepare("SELECT id FROM categories WHERE slug = ?");
                $stmt->execute([$slug]);
            }
            if (!$stmt->fetch()) break;
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }
        return $slug;
    }
}

/* ══════════════════════════════════════════════════════════
   সেফ ম্যাপার
   ══════════════════════════════════════════════════════════ */
if (!function_exists('safeMapNewsKeys')) {
    function safeMapNewsKeys($row) {
        $mapped = mapNewsKeys($row);
        foreach ($row as $key => $val) {
            if (!isset($mapped[$key]) || $mapped[$key] === null) {
                $mapped[$key] = $val;
            }
        }
        return $mapped;
    }
}

/* ══════════════════════════════════════════════════════════
   পাসওয়ার্ড জেনারেটর
   ══════════════════════════════════════════════════════════ */
if (!function_exists('generateRandomPassword')) {
    function generateRandomPassword($length = 14) {
        $upper   = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lower   = 'abcdefghjkmnpqrstuvwxyz';
        $numbers = '23456789';
        $symbols = '@#$%&*!?';
        $all     = $upper . $lower . $numbers . $symbols;
        $pass = '';
        $pass .= $upper[random_int(0, strlen($upper) - 1)];
        $pass .= $lower[random_int(0, strlen($lower) - 1)];
        $pass .= $numbers[random_int(0, strlen($numbers) - 1)];
        $pass .= $symbols[random_int(0, strlen($symbols) - 1)];
        for ($i = 4; $i < $length; $i++) {
            $pass .= $all[random_int(0, strlen($all) - 1)];
        }
        return str_shuffle($pass);
    }
}

if (!function_exists('checkPasswordStrength')) {
    function checkPasswordStrength($pass) {
        $score = 0;
        if (strlen($pass) >= 8)  $score++;
        if (strlen($pass) >= 12) $score++;
        if (strlen($pass) >= 16) $score++;
        if (preg_match('/[A-Z]/', $pass)) $score++;
        if (preg_match('/[a-z]/', $pass)) $score++;
        if (preg_match('/[0-9]/', $pass)) $score++;
        if (preg_match('/[^A-Za-z0-9]/', $pass)) $score++;
        if ($score <= 2) return ['label' => 'দুর্বল', 'color' => '#dc2626', 'percent' => 25];
        if ($score <= 4) return ['label' => 'মাঝারি', 'color' => '#ca8a04', 'percent' => 50];
        if ($score <= 5) return ['label' => 'ভালো', 'color' => '#16a34a', 'percent' => 75];
        return ['label' => 'শক্তিশালী', 'color' => '#059669', 'percent' => 100];
    }
}

/* ══════════════════════════════════════════════════════════
   টাইম অ্যাগো
   ══════════════════════════════════════════════════════════ */
if (!function_exists('timeAgo')) {
    function timeAgo($datetime) {
        if (empty($datetime)) return '';
        $now = new DateTime();
        $ago = new DateTime($datetime);
        $diff = $now->diff($ago);
        if ($diff->y > 0) return toBn($diff->y) . ' বছর আগে';
        if ($diff->m > 0) return toBn($diff->m) . ' মাস আগে';
        if ($diff->d > 0) return toBn($diff->d) . ' দিন আগে';
        if ($diff->h > 0) return toBn($diff->h) . ' ঘণ্টা আগে';
        if ($diff->i > 0) return toBn($diff->i) . ' মিনিট আগে';
        return 'এইমাত্র';
    }
}

/* ══════════════════════════════════════════════════════════
   ট্যাগ পিল রেন্ডারার
   ══════════════════════════════════════════════════════════ */
if (!function_exists('renderTagPills')) {
    function renderTagPills($tagStr, $linkPrefix = '?page=home&tag=') {
        if (empty($tagStr)) return '';
        $tags = array_filter(array_map('trim', explode(',', $tagStr)));
        $html = '';
        foreach ($tags as $tag) {
            if ($tag !== '') {
                $html .= '<a href="' . $linkPrefix . urlencode($tag) . '" class="tag-pill"><i class="fas fa-tag"></i> ' . htmlspecialchars($tag) . '</a>';
            }
        }
        return $html;
    }
}

/* ══════════════════════════════════════════════════════════
   ডিফল্ট অ্যাডমিন ইউজার অটো-সিড
   ══════════════════════════════════════════════════════════ */
try {
    $count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($count == 0) {
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (username, password, full_name) VALUES (?, ?, 'অ্যাডমিন')")->execute(['admin', $hash]);
    }
} catch (Exception $e) {}

/* ══════════════════════════════════════════════════════════
   ডিফল্ট টেবিল সৃষ্টি
   ══════════════════════════════════════════════════════════ */
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(200) DEFAULT '',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(200) NOT NULL UNIQUE,
        slug VARCHAR(250) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS news (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(500) NOT NULL,
        slug VARCHAR(600) NOT NULL UNIQUE,
        content LONGTEXT,
        image VARCHAR(500) DEFAULT '',
        tags VARCHAR(500) DEFAULT '',
        category_id INT DEFAULT NULL,
        author VARCHAR(200) DEFAULT 'অজানা',
        status ENUM('published','draft') DEFAULT 'draft',
        is_featured TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS breaking_news (
        id INT AUTO_INCREMENT PRIMARY KEY,
        text TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("ALTER TABLE news ADD COLUMN IF NOT EXISTS tags VARCHAR(500) DEFAULT '' AFTER image");
} catch (Exception $e) {}