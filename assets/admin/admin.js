/**
 * 后台公共交互：明暗模式切换（与前台默认主题共用 cb-darkmode 键，localStorage 记忆，默认跟随系统）
 * + 移动端 ☰ 抽屉式导航（固定侧栏滑入滑出 + 遮罩）
 */
(function () {
    'use strict';

    // 与前台默认主题共用同一键，前后台明暗偏好保持一致
    var STORAGE_KEY = 'cb-darkmode';
    // 与 admin.css 的媒体查询断点保持一致：≤860px 侧栏为抽屉式
    var SIDE_BREAKPOINT = 860;

    function storedPreference() {
        try { return localStorage.getItem(STORAGE_KEY); } catch (err) { return null; }
    }

    function savePreference(value) {
        try { localStorage.setItem(STORAGE_KEY, value); } catch (err) { /* 隐私模式下静默失败 */ }
    }

    function applyDarkMode(enable) {
        if (enable) {
            document.documentElement.classList.add('darkmode');
        } else {
            document.documentElement.classList.remove('darkmode');
        }
        var icon = document.getElementById('admin-darkmode-icon');
        if (icon) {
            icon.textContent = enable ? '☀' : '☾';
        }
    }

    function initDarkMode() {
        // head 内联脚本已在渲染前应用偏好（防闪烁），此处仅同步图标状态并绑定切换
        var isDark = document.documentElement.classList.contains('darkmode');
        applyDarkMode(isDark);

        var toggle = document.getElementById('admin-darkmode-toggle');
        if (toggle) {
            toggle.addEventListener('click', function () {
                var dark = document.documentElement.classList.contains('darkmode');
                applyDarkMode(!dark);
                savePreference(!dark ? 'true' : 'false');
            });
        }

        // 系统偏好变化时仅在用户未手动选择的情况下跟随（与前台主题行为一致）
        if (window.matchMedia) {
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function (ev) {
                if (storedPreference() === null) {
                    applyDarkMode(ev.matches);
                }
            });
        }
    }

    function initSidebar() {
        var toggle = document.getElementById('admin-side-toggle');
        var side = document.getElementById('admin-side');
        var overlay = document.getElementById('admin-side-overlay');
        if (!toggle || !side) {
            return;
        }

        function closeSidebar() {
            side.classList.remove('open');
            if (overlay) {
                overlay.classList.remove('open');
            }
            toggle.setAttribute('aria-expanded', 'false');
        }

        toggle.addEventListener('click', function () {
            var open = side.classList.toggle('open');
            if (overlay) {
                if (open) {
                    overlay.classList.add('open');
                } else {
                    overlay.classList.remove('open');
                }
            }
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });

        if (overlay) {
            overlay.addEventListener('click', closeSidebar);
        }
        // 移动端点击导航链接后自动收起抽屉
        var links = side.querySelectorAll('a');
        for (var i = 0; i < links.length; i++) {
            links[i].addEventListener('click', function () {
                if (window.innerWidth <= SIDE_BREAKPOINT) {
                    closeSidebar();
                }
            });
        }
    }

    function init() {
        initDarkMode();
        initSidebar();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
