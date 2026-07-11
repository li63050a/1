<?php
/**
 * 短链接跳转处理器
 * 访问 /go.php?c=XXXX 或直接 /XXXX 时，跳转到目标网址
 */

require_once __DIR__ . '/config.php';

// 获取短码
$code = '';
if (isset($_GET['c'])) {
    $code = trim($_GET['c']);
} elseif (isset($_GET['code'])) {
    $code = trim($_GET['code']);
} else {
    // 尝试从路径获取（配合 .htaccess 使用）
    $requestUri = $_SERVER['REQUEST_URI'];
    $basePath = dirname($_SERVER['SCRIPT_NAME']);
    $path = substr($requestUri, strlen($basePath));
    $path = trim($path, '/');
    // 去掉查询字符串
    if (($pos = strpos($path, '?')) !== false) {
        $path = substr($path, 0, $pos);
    }
    $code = $path;
}

// 短码为空
if (empty($code)) {
    http_response_code(404);
    include __DIR__ . '/404.html';
    exit;
}

// 只允许合法字符
if (!preg_match('/^[a-zA-Z0-9_-]+$/', $code)) {
    http_response_code(400);
    include __DIR__ . '/404.html';
    exit;
}

$db = getDB();

// 查找链接
$stmt = $db->prepare("SELECT * FROM links WHERE code = ?");
$stmt->execute([$code]);
$link = $stmt->fetch();

// 链接不存在
if (!$link) {
    http_response_code(404);
    include __DIR__ . '/404.html';
    exit;
}

// 链接已禁用
if (!$link['is_active']) {
    http_response_code(410);
    include __DIR__ . '/404.html';
    exit;
}

// 记录点击
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

$stmt = $db->prepare("INSERT INTO clicks (link_id, ip, referer, user_agent) VALUES (?, ?, ?, ?)");
$stmt->execute([$link['id'], $ip, $referer, $userAgent]);

// 更新点击数
$stmt = $db->prepare("UPDATE links SET clicks = clicks + 1 WHERE id = ?");
$stmt->execute([$link['id']]);

// 跳转
header('Location: ' . $link['url'], true, 302);
exit;
