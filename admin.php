<?php
/**
 * 短链接系统 - 管理后台
 * 功能：管理员登录、仪表盘、链接管理（查看/搜索/编辑/删除/启禁用）
 */

require_once __DIR__ . '/config.php';

$siteUrl = getSiteUrl();
$action = $_GET['action'] ?? '';

// ===== 退出登录 =====
if ($action === 'logout') {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// ===== 处理 AJAX 请求 =====
if ($action === 'api') {
    header('Content-Type: application/json; charset=utf-8');

    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => '未登录']);
        exit;
    }

    $apiAction = $_POST['api_action'] ?? '';
    $db = getDB();

    switch ($apiAction) {
        // 删除链接
        case 'delete':
            $id = intval($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $db->prepare("DELETE FROM clicks WHERE link_id = ?");
                $stmt->execute([$id]);
                $stmt = $db->prepare("DELETE FROM links WHERE id = ?");
                $stmt->execute([$id]);
                echo json_encode(['success' => true, 'message' => '已删除']);
            } else {
                echo json_encode(['success' => false, 'message' => '无效的参数']);
            }
            break;

        // 切换启禁用状态
        case 'toggle':
            $id = intval($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $db->prepare("UPDATE links SET is_active = NOT is_active WHERE id = ?");
                $stmt->execute([$id]);
                $stmt = $db->prepare("SELECT is_active FROM links WHERE id = ?");
                $stmt->execute([$id]);
                $link = $stmt->fetch();
                echo json_encode(['success' => true, 'message' => '已更新', 'is_active' => $link['is_active']]);
            } else {
                echo json_encode(['success' => false, 'message' => '无效的参数']);
            }
            break;

        // 编辑链接
        case 'edit':
            $id = intval($_POST['id'] ?? 0);
            $newUrl = trim($_POST['url'] ?? '');
            if ($id > 0 && !empty($newUrl)) {
                if (!isValidUrl($newUrl)) {
                    echo json_encode(['success' => false, 'message' => '网址格式不正确']);
                } else {
                    $stmt = $db->prepare("UPDATE links SET url = ? WHERE id = ?");
                    $stmt->execute([$newUrl, $id]);
                    echo json_encode(['success' => true, 'message' => '已更新']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => '无效的参数']);
            }
            break;

        // 搜索链接
        case 'search':
            $keyword = trim($_POST['keyword'] ?? '');
            $page = max(1, intval($_POST['page'] ?? 1));
            $perPage = 15;
            $offset = ($page - 1) * $perPage;

            if (!empty($keyword)) {
                $like = '%' . $keyword . '%';
                $stmt = $db->prepare("SELECT * FROM links WHERE code LIKE ? OR url LIKE ? ORDER BY id DESC LIMIT ? OFFSET ?");
                $stmt->execute([$like, $like, $perPage, $offset]);
                $links = $stmt->fetchAll();

                $stmt = $db->prepare("SELECT COUNT(*) FROM links WHERE code LIKE ? OR url LIKE ?");
                $stmt->execute([$like, $like]);
                $total = $stmt->fetchColumn();
            } else {
                $stmt = $db->prepare("SELECT * FROM links ORDER BY id DESC LIMIT ? OFFSET ?");
                $stmt->execute([$perPage, $offset]);
                $links = $stmt->fetchAll();

                $total = $db->query("SELECT COUNT(*) FROM links")->fetchColumn();
            }

            $totalPages = max(1, ceil($total / $perPage));

            echo json_encode([
                'success' => true,
                'links' => $links,
                'total' => (int)$total,
                'page' => $page,
                'total_pages' => $totalPages,
                'site_url' => $siteUrl
            ]);
            break;

        // 获取点击详情
        case 'clicks_detail':
            $id = intval($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $db->prepare("SELECT * FROM clicks WHERE link_id = ? ORDER BY clicked_at DESC LIMIT 50");
                $stmt->execute([$id]);
                $clicks = $stmt->fetchAll();
                echo json_encode(['success' => true, 'clicks' => $clicks]);
            } else {
                echo json_encode(['success' => false, 'message' => '无效的参数']);
            }
            break;

        // 修改密码
        case 'change_password':
            $oldPwd = $_POST['old_password'] ?? '';
            $newPwd = $_POST['new_password'] ?? '';
            if (strlen($newPwd) < 6) {
                echo json_encode(['success' => false, 'message' => '新密码至少 6 位']);
            } else {
                $stmt = $db->prepare("SELECT password FROM admins WHERE username = ?");
                $stmt->execute([$_SESSION['admin_username']]);
                $admin = $stmt->fetch();
                if ($admin && password_verify($oldPwd, $admin['password'])) {
                    $hash = password_hash($newPwd, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE admins SET password = ? WHERE username = ?");
                    $stmt->execute([$hash, $_SESSION['admin_username']]);
                    echo json_encode(['success' => true, 'message' => '密码已修改']);
                } else {
                    echo json_encode(['success' => false, 'message' => '原密码错误']);
                }
            }
            break;

        default:
            echo json_encode(['success' => false, 'message' => '未知操作']);
    }
    exit;
}

// ===== 处理登录 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $captchaInput = strtolower(trim($_POST['captcha'] ?? ''));
    $captchaSession = $_SESSION['captcha_code'] ?? '';

    $loginError = '';

    if (empty($captchaInput) || $captchaInput !== $captchaSession) {
        $loginError = '验证码错误';
    }
    unset($_SESSION['captcha_code']);

    if (!$loginError) {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_id'] = $admin['id'];
            header('Location: admin.php');
            exit;
        } else {
            $loginError = '用户名或密码错误';
        }
    }
}

// ===== 如果未登录，显示登录页 =====
if (!isLoggedIn()) {
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>管理员登录 - 短链接系统</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-page">
<div class="login-container">
    <div class="card login-card">
        <div class="login-header">
            <h1>🔐 管理员登录</h1>
            <p>登录以管理短链接系统</p>
        </div>

        <?php if (!empty($loginError)): ?>
        <div class="alert alert-error">
            <span class="alert-icon">⚠️</span>
            <span><?php echo htmlspecialchars($loginError); ?></span>
        </div>
        <?php endif; ?>

        <form method="POST" action="admin.php?action=login" id="loginForm">
            <div class="form-group">
                <label for="username">用户名</label>
                <input type="text" id="username" name="username" placeholder="请输入管理员用户名" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">密码</label>
                <input type="password" id="password" name="password" placeholder="请输入密码" required>
            </div>
            <div class="form-group captcha-group">
                <label for="captcha">人机验证</label>
                <div class="captcha-row">
                    <input type="text" id="captcha" name="captcha" placeholder="请输入验证码" required autocomplete="off" maxlength="4">
                    <img src="captcha.php" alt="验证码" id="captchaImg" title="点击刷新验证码" onclick="refreshCaptcha()">
                    <a href="javascript:void(0)" class="captcha-refresh" onclick="refreshCaptcha()" title="刷新">🔄</a>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-lg btn-block">登录</button>
            </div>
        </form>
        <div class="login-footer">
            <a href="index.php">← 返回首页</a>
        </div>
    </div>
</div>
<script src="assets/script.js"></script>
</body>
</html>
<?php
    exit;
}

// ===== 已登录 - 获取仪表盘数据 =====
$db = getDB();

// 统计数据
$totalLinks = $db->query("SELECT COUNT(*) FROM links")->fetchColumn();
$totalClicks = $db->query("SELECT SUM(clicks) FROM links")->fetchColumn() ?: 0;
$todayClicks = $db->query("SELECT COUNT(*) FROM clicks WHERE date(clicked_at) = date('now','localtime')")->fetchColumn();
$activeLinks = $db->query("SELECT COUNT(*) FROM links WHERE is_active = 1")->fetchColumn();

// 获取链接列表（第一页）
$perPage = 15;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;
$searchKeyword = trim($_GET['search'] ?? '');

if (!empty($searchKeyword)) {
    $like = '%' . $searchKeyword . '%';
    $stmt = $db->prepare("SELECT * FROM links WHERE code LIKE ? OR url LIKE ? ORDER BY id DESC LIMIT ? OFFSET ?");
    $stmt->execute([$like, $like, $perPage, $offset]);
    $links = $stmt->fetchAll();

    $stmt = $db->prepare("SELECT COUNT(*) FROM links WHERE code LIKE ? OR url LIKE ?");
    $stmt->execute([$like, $like]);
    $totalForPages = $stmt->fetchColumn();
} else {
    $links = $db->query("SELECT * FROM links ORDER BY id DESC LIMIT $perPage OFFSET $offset")->fetchAll();
    $totalForPages = $totalLinks;
}
$totalPages = max(1, ceil($totalForPages / $perPage));
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>管理后台 - 短链接系统</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="admin-page">

<!-- 顶部导航 -->
<nav class="navbar navbar-admin">
    <div class="container nav-content">
        <a href="admin.php" class="logo">
            <span class="logo-icon">⚙️</span>
            <span>管理后台</span>
        </a>
        <div class="nav-links">
            <a href="index.php" class="nav-btn nav-btn-outline">前台首页</a>
            <span class="nav-user">👤 <?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
            <a href="admin.php?action=logout" class="nav-btn nav-btn-danger">退出</a>
        </div>
    </div>
</nav>

<main class="admin-main">
<div class="container">

    <!-- 统计卡片 -->
    <div class="stats-grid">
        <div class="stat-card stat-blue">
            <div class="stat-icon">🔗</div>
            <div class="stat-info">
                <div class="stat-number"><?php echo number_format($totalLinks); ?></div>
                <div class="stat-label">总链接数</div>
            </div>
        </div>
        <div class="stat-card stat-green">
            <div class="stat-icon">👆</div>
            <div class="stat-info">
                <div class="stat-number"><?php echo number_format($totalClicks); ?></div>
                <div class="stat-label">总点击量</div>
            </div>
        </div>
        <div class="stat-card stat-orange">
            <div class="stat-icon">📈</div>
            <div class="stat-info">
                <div class="stat-number"><?php echo number_format($todayClicks); ?></div>
                <div class="stat-label">今日点击</div>
            </div>
        </div>
        <div class="stat-card stat-purple">
            <div class="stat-icon">✅</div>
            <div class="stat-info">
                <div class="stat-number"><?php echo number_format($activeLinks); ?></div>
                <div class="stat-label">活跃链接</div>
            </div>
        </div>
    </div>

    <!-- 工具栏 -->
    <div class="toolbar">
        <div class="search-box">
            <form method="GET" action="admin.php" id="searchForm">
                <input type="text" name="search" id="searchInput" placeholder="搜索短码或网址..." value="<?php echo htmlspecialchars($searchKeyword); ?>">
                <button type="submit" class="btn btn-primary btn-sm">🔍 搜索</button>
                <?php if (!empty($searchKeyword)): ?>
                <a href="admin.php" class="btn btn-secondary btn-sm">✖ 清除</a>
                <?php endif; ?>
            </form>
        </div>
        <div class="toolbar-actions">
            <button type="button" class="btn btn-secondary btn-sm" onclick="showChangePwd()">🔑 修改密码</button>
        </div>
    </div>

    <!-- 修改密码弹窗 -->
    <div class="modal" id="pwdModal" style="display:none">
        <div class="modal-overlay" onclick="closeModal('pwdModal')"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h3>🔑 修改密码</h3>
                <button class="modal-close" onclick="closeModal('pwdModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>原密码</label>
                    <input type="password" id="oldPassword" placeholder="请输入原密码">
                </div>
                <div class="form-group">
                    <label>新密码</label>
                    <input type="password" id="newPassword" placeholder="请输入新密码（至少6位）">
                </div>
                <div id="pwdMsg"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('pwdModal')">取消</button>
                <button class="btn btn-primary" onclick="changePassword()">确认修改</button>
            </div>
        </div>
    </div>

    <!-- 编辑弹窗 -->
    <div class="modal" id="editModal" style="display:none">
        <div class="modal-overlay" onclick="closeModal('editModal')"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h3>✏️ 编辑链接</h3>
                <button class="modal-close" onclick="closeModal('editModal')">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editId">
                <div class="form-group">
                    <label>目标网址</label>
                    <input type="url" id="editUrl" placeholder="请输入新的目标网址">
                </div>
                <div id="editMsg"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('editModal')">取消</button>
                <button class="btn btn-primary" onclick="saveEdit()">保存</button>
            </div>
        </div>
    </div>

    <!-- 点击详情弹窗 -->
    <div class="modal" id="clicksModal" style="display:none">
        <div class="modal-overlay" onclick="closeModal('clicksModal')"></div>
        <div class="modal-content modal-lg">
            <div class="modal-header">
                <h3>📊 点击记录</h3>
                <button class="modal-close" onclick="closeModal('clicksModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div id="clicksContent">加载中...</div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('clicksModal')">关闭</button>
            </div>
        </div>
    </div>

    <!-- 链接列表 -->
    <div class="card table-card">
        <div class="table-header">
            <h2>📋 链接列表</h2>
            <span class="badge">共 <?php echo $totalForPages; ?> 条</span>
        </div>

        <div class="table-responsive">
            <table class="data-table" id="linksTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>短码</th>
                        <th>目标网址</th>
                        <th>点击数</th>
                        <th>状态</th>
                        <th>创建时间</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($links)): ?>
                    <tr>
                        <td colspan="7" class="empty-state">暂无链接数据</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($links as $link): ?>
                    <tr id="row-<?php echo $link['id']; ?>" data-id="<?php echo $link['id']; ?>">
                        <td class="td-id"><?php echo $link['id']; ?></td>
                        <td>
                            <code class="code-badge"><?php echo htmlspecialchars($link['code']); ?></code>
                            <?php if ($link['custom_alias']): ?>
                                <span class="badge badge-sm badge-info">自定义</span>
                            <?php endif; ?>
                        </td>
                        <td class="td-url">
                            <a href="<?php echo htmlspecialchars($link['url']); ?>" target="_blank" rel="noopener" title="<?php echo htmlspecialchars($link['url']); ?>">
                                <?php echo htmlspecialchars(mb_strimwidth($link['url'], 0, 50, '...')); ?>
                            </a>
                        </td>
                        <td>
                            <a href="javascript:void(0)" class="clicks-link" onclick="showClicks(<?php echo $link['id']; ?>)">
                                <?php echo number_format($link['clicks']); ?>
                            </a>
                        </td>
                        <td>
                            <span class="status-badge <?php echo $link['is_active'] ? 'status-active' : 'status-inactive'; ?>" id="status-<?php echo $link['id']; ?>">
                                <?php echo $link['is_active'] ? '启用' : '禁用'; ?>
                            </span>
                        </td>
                        <td class="td-date"><?php echo $link['created_at']; ?></td>
                        <td class="td-actions">
                            <button class="btn-icon btn-toggle" title="切换状态" onclick="toggleLink(<?php echo $link['id']; ?>)" id="toggle-<?php echo $link['id']; ?>">
                                <?php echo $link['is_active'] ? '⏸️' : '▶️'; ?>
                            </button>
                            <button class="btn-icon btn-edit" title="编辑" onclick="editLink(<?php echo $link['id']; ?>, '<?php echo htmlspecialchars(addslashes($link['url'])); ?>')">✏️</button>
                            <button class="btn-icon btn-copy" title="复制短链" onclick="copyLink('<?php echo $siteUrl; ?>/go.php?c=<?php echo $link['code']; ?>')">📋</button>
                            <button class="btn-icon btn-delete" title="删除" onclick="deleteLink(<?php echo $link['id']; ?>)">🗑️</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- 分页 -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?><?php echo $searchKeyword ? '&search=' . urlencode($searchKeyword) : ''; ?>" class="page-btn">« 上一页</a>
            <?php endif; ?>

            <?php
            $start = max(1, $page - 2);
            $end = min($totalPages, $page + 2);
            if ($start > 1): ?>
                <a href="?page=1<?php echo $searchKeyword ? '&search=' . urlencode($searchKeyword) : ''; ?>" class="page-btn">1</a>
                <?php if ($start > 2): ?><span class="page-dots">...</span><?php endif; ?>
            <?php endif;

            for ($i = $start; $i <= $end; $i++): ?>
                <a href="?page=<?php echo $i; ?><?php echo $searchKeyword ? '&search=' . urlencode($searchKeyword) : ''; ?>" class="page-btn <?php echo $i === $page ? 'page-active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor;

            if ($end < $totalPages): ?>
                <?php if ($end < $totalPages - 1): ?><span class="page-dots">...</span><?php endif; ?>
                <a href="?page=<?php echo $totalPages; ?><?php echo $searchKeyword ? '&search=' . urlencode($searchKeyword) : ''; ?>" class="page-btn"><?php echo $totalPages; ?></a>
            <?php endif; ?>

            <?php if ($page < $totalPages): ?>
                <a href="?page=<?php echo $page + 1; ?><?php echo $searchKeyword ? '&search=' . urlencode($searchKeyword) : ''; ?>" class="page-btn">下一页 »</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

</div>
</main>

<script src="assets/script.js"></script>
<script>
// 管理后台专用脚本
var siteUrl = <?php echo json_encode($siteUrl); ?>;

function toggleLink(id) {
    if (!confirm('确定要切换该链接的启用/禁用状态吗？')) return;
    postApi({api_action: 'toggle', id: id}, function(res) {
        if (res.success) {
            var badge = document.getElementById('status-' + id);
            var btn = document.getElementById('toggle-' + id);
            if (res.is_active == 1) {
                badge.textContent = '启用';
                badge.className = 'status-badge status-active';
                btn.innerHTML = '⏸️';
            } else {
                badge.textContent = '禁用';
                badge.className = 'status-badge status-inactive';
                btn.innerHTML = '▶️';
            }
        } else {
            alert(res.message);
        }
    });
}

function deleteLink(id) {
    if (!confirm('确定要删除该链接吗？此操作不可恢复！')) return;
    postApi({api_action: 'delete', id: id}, function(res) {
        if (res.success) {
            var row = document.getElementById('row-' + id);
            if (row) {
                row.style.transition = 'opacity 0.3s';
                row.style.opacity = '0';
                setTimeout(function() { row.remove(); }, 300);
            }
        } else {
            alert(res.message);
        }
    });
}

function editLink(id, url) {
    document.getElementById('editId').value = id;
    document.getElementById('editUrl').value = url;
    document.getElementById('editMsg').innerHTML = '';
    showModal('editModal');
}

function saveEdit() {
    var id = document.getElementById('editId').value;
    var url = document.getElementById('editUrl').value.trim();
    if (!url) {
        document.getElementById('editMsg').innerHTML = '<div class="alert alert-error">网址不能为空</div>';
        return;
    }
    postApi({api_action: 'edit', id: id, url: url}, function(res) {
        if (res.success) {
            closeModal('editModal');
            location.reload();
        } else {
            document.getElementById('editMsg').innerHTML = '<div class="alert alert-error">' + res.message + '</div>';
        }
    });
}

function showClicks(id) {
    showModal('clicksModal');
    document.getElementById('clicksContent').innerHTML = '<p>加载中...</p>';
    postApi({api_action: 'clicks_detail', id: id}, function(res) {
        if (res.success && res.clicks.length > 0) {
            var html = '<table class="data-table"><thead><tr><th>时间</th><th>IP</th><th>来源</th></tr></thead><tbody>';
            res.clicks.forEach(function(c) {
                html += '<tr>';
                html += '<td>' + c.clicked_at + '</td>';
                html += '<td>' + (c.ip || '-') + '</td>';
                html += '<td>' + (c.referer ? '<a href="' + c.referer + '" target="_blank">' + c.referer.substring(0,40) + '</a>' : '-') + '</td>';
                html += '</tr>';
            });
            html += '</tbody></table>';
            document.getElementById('clicksContent').innerHTML = html;
        } else {
            document.getElementById('clicksContent').innerHTML = '<p class="empty-state">暂无点击记录</p>';
        }
    });
}

function showChangePwd() {
    document.getElementById('oldPassword').value = '';
    document.getElementById('newPassword').value = '';
    document.getElementById('pwdMsg').innerHTML = '';
    showModal('pwdModal');
}

function changePassword() {
    var oldPwd = document.getElementById('oldPassword').value;
    var newPwd = document.getElementById('newPassword').value;
    if (!oldPwd || !newPwd) {
        document.getElementById('pwdMsg').innerHTML = '<div class="alert alert-error">请填写完整</div>';
        return;
    }
    postApi({api_action: 'change_password', old_password: oldPwd, new_password: newPwd}, function(res) {
        if (res.success) {
            document.getElementById('pwdMsg').innerHTML = '<div class="alert alert-success">' + res.message + '</div>';
            setTimeout(function() { closeModal('pwdModal'); }, 1500);
        } else {
            document.getElementById('pwdMsg').innerHTML = '<div class="alert alert-error">' + res.message + '</div>';
        }
    });
}

function postApi(data, callback) {
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'admin.php?action=api');
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
        try {
            var res = JSON.parse(xhr.responseText);
            callback(res);
        } catch(e) {
            alert('请求失败，请重试');
        }
    };
    xhr.onerror = function() { alert('网络错误'); };
    var params = [];
    for (var k in data) {
        params.push(encodeURIComponent(k) + '=' + encodeURIComponent(data[k]));
    }
    xhr.send(params.join('&'));
}
</script>

</body>
</html>
