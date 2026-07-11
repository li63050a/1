/**
 * 短链接系统 - 前端脚本
 */

// ========== 验证码刷新 ==========
function refreshCaptcha() {
    var img = document.getElementById('captchaImg');
    if (img) {
        img.src = 'captcha.php?t=' + Date.now();
    }
}

// ========== 复制短链接 ==========
function copyShortUrl() {
    var input = document.getElementById('shortUrl');
    if (!input) return;

    // 选中并复制
    input.select();
    input.setSelectionRange(0, 99999);

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(input.value).then(function() {
            showCopySuccess();
        }).catch(function() {
            fallbackCopy();
        });
    } else {
        fallbackCopy();
    }
}

function fallbackCopy() {
    try {
        document.execCommand('copy');
        showCopySuccess();
    } catch (e) {
        alert('复制失败，请手动复制');
    }
}

function showCopySuccess() {
    var btn = document.querySelector('.btn-copy');
    var copyText = document.getElementById('copyText');
    if (btn && copyText) {
        btn.classList.add('copied');
        copyText.textContent = '✅ 已复制';
        setTimeout(function() {
            btn.classList.remove('copied');
            copyText.textContent = '📋 复制';
        }, 2000);
    }
}

// ========== 管理后台复制链接 ==========
function copyLink(url) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(function() {
            showToast('已复制到剪贴板');
        }).catch(function() {
            fallbackCopyLink(url);
        });
    } else {
        fallbackCopyLink(url);
    }
}

function fallbackCopyLink(url) {
    var ta = document.createElement('textarea');
    ta.value = url;
    ta.style.position = 'fixed';
    ta.style.left = '-9999px';
    document.body.appendChild(ta);
    ta.select();
    try {
        document.execCommand('copy');
        showToast('已复制到剪贴板');
    } catch (e) {
        alert('复制失败，请手动复制：' + url);
    }
    document.body.removeChild(ta);
}

// ========== 简易提示条 ==========
function showToast(msg) {
    // 移除已有的
    var old = document.querySelector('.toast-msg');
    if (old) old.remove();

    var toast = document.createElement('div');
    toast.className = 'toast-msg';
    toast.textContent = msg;
    toast.style.cssText = 'position:fixed;bottom:30px;left:50%;transform:translateX(-50%);background:#2c3e50;color:#fff;padding:12px 28px;border-radius:30px;font-size:0.92rem;z-index:9999;box-shadow:0 4px 15px rgba(0,0,0,0.2);animation:toastIn 0.3s ease;';
    document.body.appendChild(toast);
    setTimeout(function() {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.3s';
        setTimeout(function() { toast.remove(); }, 300);
    }, 2000);
}

// 添加 toast 动画
var toastStyle = document.createElement('style');
toastStyle.textContent = '@keyframes toastIn{from{opacity:0;transform:translateX(-50%) translateY(15px)}to{opacity:1;transform:translateX(-50%) translateY(0)}}';
document.head.appendChild(toastStyle);

// ========== 自定义短码切换 ==========
function toggleAlias() {
    var group = document.getElementById('aliasGroup');
    var btn = document.getElementById('toggleAliasBtn');
    if (!group) return;

    if (group.style.display === 'none') {
        group.style.display = 'block';
        group.style.animation = 'fadeIn 0.3s ease';
        if (btn) btn.textContent = '⚙️ 收起自定义';
    } else {
        group.style.display = 'none';
        if (btn) btn.textContent = '⚙️ 自定义短码';
    }
}

// 添加淡入动画
var fadeStyle = document.createElement('style');
fadeStyle.textContent = '@keyframes fadeIn{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}';
document.head.appendChild(fadeStyle);

// ========== 重置表单 ==========
function resetForm() {
    var form = document.getElementById('shortenForm');
    var result = document.querySelector('.result-box');
    if (form) form.style.display = 'block';
    if (result) result.style.display = 'none';
    if (form) form.reset();
    refreshCaptcha();

    // 重置自定义别名
    var aliasGroup = document.getElementById('aliasGroup');
    var toggleBtn = document.getElementById('toggleAliasBtn');
    if (aliasGroup) aliasGroup.style.display = 'none';
    if (toggleBtn) toggleBtn.textContent = '⚙️ 自定义短码';
}

// ========== 弹窗管理 ==========
function showModal(id) {
    var modal = document.getElementById(id);
    if (modal) modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeModal(id) {
    var modal = document.getElementById(id);
    if (modal) modal.style.display = 'none';
    document.body.style.overflow = '';
}

// 按 ESC 关闭弹窗
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        var modals = document.querySelectorAll('.modal');
        modals.forEach(function(m) {
            if (m.style.display !== 'none') {
                m.style.display = 'none';
            }
        });
        document.body.style.overflow = '';
    }
});

// ========== 表单验证增强 ==========
document.addEventListener('DOMContentLoaded', function() {
    // 验证码输入自动转大写
    var captchaInput = document.getElementById('captcha');
    if (captchaInput) {
        captchaInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^a-zA-Z0-9]/g, '');
        });
    }

    // 网址输入实时验证
    var urlInput = document.getElementById('url');
    if (urlInput) {
        urlInput.addEventListener('blur', function() {
            var val = this.value.trim();
            if (val && !val.match(/^https?:\/\//i)) {
                this.value = 'https://' + val;
            }
        });
    }
});
