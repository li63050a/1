<?php
/**
 * 短链接系统 - 首页
 * 用户在此提交长链接，生成短链接
 */

require_once __DIR__ . '/config.php';

$siteUrl = getSiteUrl();
$result = null;  // 成功时的结果
$error = null;   // 错误信息
$isLoggedIn = isLoggedIn();

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $url = trim($_POST['url'] ?? '');
    $customAlias = trim($_POST['custom_alias'] ?? '');
    $captchaInput = strtolower(trim($_POST['captcha'] ?? ''));
    $captchaSession = $_SESSION['captcha_code'] ?? '';

    // ===== 验证 =====

    // 验证码校验
    if (empty($captchaInput) || $captchaInput !== $captchaSession) {
        $error = '验证码错误，请重新输入喵~';
    }
    // 清空验证码（一次性使用）
    unset($_SESSION['captcha_code']);

    // 网址校验
    if (!$error) {
        if (empty($url)) {
            $error = '请输入要缩短的网址';
        } elseif (!isValidUrl($url)) {
            $error = '网址格式不正确，请输入完整的网址（包含 http:// 或 https://）';
        }
    }

    // ===== 生成短链接 =====
    if (!$error) {
        $db = getDB();

        // 检查是否已存在相同的网址
        $stmt = $db->prepare("SELECT code, url FROM links WHERE url = ? AND is_active = 1");
        $stmt->execute([$url]);
        $existing = $stmt->fetch();

        if ($existing) {
            $result = [
                'short_url' => $siteUrl . '/go.php?c=' . $existing['code'],
                'code' => $existing['code'],
                'original_url' => $url,
                'is_existing' => true
            ];
        } else {
            // 确定短码
            $code = '';
            $isCustom = false;

            if (!empty($customAlias)) {
                // 自定义别名
                if (!preg_match('/^[a-zA-Z0-9_-]+$/', $customAlias)) {
                    $error = '自定义别名只能包含字母、数字、下划线和短横线';
                } elseif (strlen($customAlias) < 2 || strlen($customAlias) > 20) {
                    $error = '自定义别名长度需在 2~20 个字符之间';
                } elseif (codeExists($customAlias)) {
                    $error = '该自定义别名已被使用，请换一个吧';
                } else {
                    $code = $customAlias;
                    $isCustom = true;
                }
            }

            if (!$error) {
                if (empty($code)) {
                    // 生成随机短码
                    do {
                        $code = generateCode();
                    } while (codeExists($code));
                }

                // 存入数据库
                $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                $stmt = $db->prepare("INSERT INTO links (code, url, ip, custom_alias) VALUES (?, ?, ?, ?)");
                $stmt->execute([$code, $url, $ip, $isCustom ? 1 : 0]);

                $result = [
                    'short_url' => $siteUrl . '/go.php?c=' . $code,
                    'code' => $code,
                    'original_url' => $url,
                    'is_existing' => false
                ];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>短链接系统 - 一键生成短链接</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>

<!-- 顶部导航 -->
<nav class="navbar">
    <div class="container nav-content">
        <a href="." class="logo">
            <span class="logo-icon">🔗</span>
            <span>短链接系统</span>
        </a>
        <div class="nav-links">
            <?php if ($isLoggedIn): ?>
                <a href="admin.php" class="nav-btn">管理后台</a>
                <a href="admin.php?action=logout" class="nav-btn nav-btn-outline">退出</a>
            <?php else: ?>
                <a href="admin.php" class="nav-btn nav-btn-outline">管理员登录</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<!-- 主区域 -->
<main class="main-section">
    <div class="container">
        <div class="hero">
            <h1>🐾 短链接生成器</h1>
            <p class="subtitle">粘贴长链接，一键生成简洁的短链接，方便分享</p>
        </div>

        <div class="card form-card">
            <!-- 错误提示 -->
            <?php if ($error): ?>
            <div class="alert alert-error">
                <span class="alert-icon">⚠️</span>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
            <?php endif; ?>

            <!-- 成功结果 -->
            <?php if ($result): ?>
            <div class="result-box">
                <div class="result-success">
                    <span class="result-icon">🎉</span>
                    <span>短链接生成成功<?php echo $result['is_existing'] ? '（该网址已有短链接）' : ''; ?>！</span>
                </div>
                <div class="short-url-display">
                    <input type="text" id="shortUrl" value="<?php echo htmlspecialchars($result['short_url']); ?>" readonly>
                    <button type="button" class="btn btn-copy" onclick="copyShortUrl()">
                        <span id="copyText">📋 复制</span>
                    </button>
                </div>
                <div class="result-meta">
                    <p><strong>原始链接：</strong><a href="<?php echo htmlspecialchars($result['original_url']); ?>" target="_blank" rel="noopener" class="original-link"><?php echo htmlspecialchars(mb_strimwidth($result['original_url'], 0, 60, '...')); ?></a></p>
                    <p><strong>短码：</strong><code><?php echo htmlspecialchars($result['code']); ?></code></p>
                </div>
                <div class="result-actions">
                    <button type="button" class="btn btn-primary" onclick="resetForm()">继续生成</button>
                </div>
            </div>
            <?php endif; ?>

            <!-- 表单 -->
            <form method="POST" action="" id="shortenForm" <?php echo $result ? 'style="display:none"' : ''; ?>>
                <div class="form-group">
                    <label for="url">目标网址</label>
                    <input type="url" id="url" name="url" placeholder="请输入完整的网址，例如 https://example.com/very/long/url" required value="<?php echo $error ? htmlspecialchars($_POST['url'] ?? '') : ''; ?>" autofocus>
                </div>

                <!-- 自定义别名（默认隐藏） -->
                <div class="form-group" id="aliasGroup" style="display:none">
                    <label for="custom_alias">自定义短码（可选）</label>
                    <input type="text" id="custom_alias" name="custom_alias" placeholder="字母、数字、下划线、短横线，2~20位" value="<?php echo $error ? htmlspecialchars($_POST['custom_alias'] ?? '') : ''; ?>">
                    <small class="form-hint">留空则自动生成随机短码</small>
                </div>

                <div class="form-group captcha-group">
                    <label for="captcha">人机验证</label>
                    <div class="captcha-row">
                        <input type="text" id="captcha" name="captcha" placeholder="请输入右侧验证码" required autocomplete="off" maxlength="4">
                        <img src="captcha.php" alt="验证码" id="captchaImg" title="点击刷新验证码" onclick="refreshCaptcha()">
                        <a href="javascript:void(0)" class="captcha-refresh" onclick="refreshCaptcha()" title="刷新验证码">🔄</a>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-lg">🚀 生成短链接</button>
                    <a href="javascript:void(0)" class="toggle-alias" onclick="toggleAlias()" id="toggleAliasBtn">⚙️ 自定义短码</a>
                </div>
            </form>
        </div>

        <!-- 使用说明 -->
        <div class="features">
            <div class="feature-card">
                <div class="feature-icon">⚡</div>
                <h3>快速生成</h3>
                <p>输入长链接，瞬间生成简洁短码</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">📊</div>
                <h3>数据统计</h3>
                <p>实时追踪每个链接的点击次数</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🛡️</div>
                <h3>安全可靠</h3>
                <p>人机验证防护，管理后台安全管控</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">✨</div>
                <h3>自定义短码</h3>
                <p>支持自定义短码，打造专属链接</p>
            </div>
        </div>
    </div>
</main>

<!-- 页脚 -->
<footer class="footer">
    <div class="container">
        <p>短链接系统 &copy; <?php echo date('Y'); ?> | 安全、快速、可靠</p>
    </div>
</footer>

<script src="assets/script.js"></script>
</body>
</html>
