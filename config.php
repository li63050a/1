<?php
/**
 * 短链接系统 - 配置文件
 */

session_start();

// ========== 基础配置 ==========
define('SITE_URL', ''); // 留空则自动检测
define('CODE_LENGTH', 6); // 短码长度
define('CODE_CHARS', 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789');

// ========== 数据库连接 ==========
function getDB() {
    static $db = null;
    if ($db === null) {
        $dataDir = __DIR__ . '/data';
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
        $db = new PDO('sqlite:' . $dataDir . '/shortlink.db');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }
    return $db;
}

// ========== 初始化数据库 ==========
function initDB() {
    $db = getDB();

    // 链接表
    $db->exec("CREATE TABLE IF NOT EXISTS links (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code TEXT UNIQUE NOT NULL,
        url TEXT NOT NULL,
        created_at DATETIME DEFAULT (datetime('now','localtime')),
        clicks INTEGER DEFAULT 0,
        is_active INTEGER DEFAULT 1,
        ip TEXT,
        custom_alias INTEGER DEFAULT 0
    )");

    // 点击记录表
    $db->exec("CREATE TABLE IF NOT EXISTS clicks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        link_id INTEGER NOT NULL,
        clicked_at DATETIME DEFAULT (datetime('now','localtime')),
        ip TEXT,
        referer TEXT,
        user_agent TEXT,
        FOREIGN KEY (link_id) REFERENCES links(id) ON DELETE CASCADE
    )");

    // 管理员表
    $db->exec("CREATE TABLE IF NOT EXISTS admins (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        created_at DATETIME DEFAULT (datetime('now','localtime'))
    )");

    // 创建默认管理员 admin / admin123
    $stmt = $db->prepare("SELECT COUNT(*) FROM admins WHERE username = ?");
    $stmt->execute(['admin']);
    if ($stmt->fetchColumn() == 0) {
        $stmt = $db->prepare("INSERT INTO admins (username, password) VALUES (?, ?)");
        $stmt->execute(['admin', password_hash('admin123', PASSWORD_DEFAULT)]);
    }
}

// ========== 辅助函数 ==========

// 获取站点地址
function getSiteUrl() {
    if (SITE_URL) return SITE_URL;
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $script = dirname($_SERVER['SCRIPT_NAME']);
    return $protocol . '://' . $host . ($script === '/' ? '' : $script);
}

// 生成随机短码
function generateCode($length = CODE_LENGTH) {
    $chars = CODE_CHARS;
    $code = '';
    $max = strlen($chars) - 1;
    for ($i = 0; $i < $length; $i++) {
        $code .= $chars[random_int(0, $max)];
    }
    return $code;
}

// 检查短码是否已存在
function codeExists($code) {
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) FROM links WHERE code = ?");
    $stmt->execute([$code]);
    return $stmt->fetchColumn() > 0;
}

// 验证网址格式
function isValidUrl($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

// 检查是否已登录
function isLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

// 要求登录
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: admin.php');
        exit;
    }
}

// 初始化数据库
initDB();
