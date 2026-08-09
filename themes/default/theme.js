/**
 * 默认主题交互：☾ 明暗切换（localStorage 记忆，默认跟随系统）+ ☰ 移动端侧栏
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'cb-darkmode';

    function storedPreference() {
        try { return localStorage.getItem(STORAGE_KEY); } catch (err) { return null; }
    }

    function savePreference(value) {
        try { localStorage.setItem(STORAGE_KEY, value); } catch (err) { /* 隐私模式下静默失败 */ }
    }

    function updateIcon(isDark) {
        var icon = document.getElementById('darkmode-icon');
        if (icon) {
            icon.textContent = isDark ? '☀' : '☾';
        }
    }

    function applyDarkMode(enable) {
        if (enable) {
            document.documentElement.classList.add('darkmode');
        } else {
            document.documentElement.classList.remove('darkmode');
        }
        updateIcon(enable);
    }

    function initDarkMode() {
        var stored = storedPreference();
        if (stored === 'true') {
            applyDarkMode(true);
        } else if (stored === 'false') {
            applyDarkMode(false);
        } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            // 未手动选择过：跟随系统偏好
            applyDarkMode(true);
        }

        var toggle = document.getElementById('darkmode-toggle');
        if (toggle) {
            toggle.addEventListener('click', function () {
                var isDark = document.documentElement.classList.contains('darkmode');
                applyDarkMode(!isDark);
                savePreference(!isDark ? 'true' : 'false');
            });
        }

        // 系统偏好变化时仅在用户未手动选择的情况下跟随
        if (window.matchMedia) {
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function (ev) {
                if (storedPreference() === null) {
                    applyDarkMode(ev.matches);
                }
            });
        }
    }

    function initSidebar() {
        var toggle = document.getElementById('sidebar-toggle');
        var sidebar = document.getElementById('sidebar');
        var overlay = document.getElementById('sidebar-overlay');
        if (!toggle || !sidebar) {
            return;
        }

        function closeSidebar() {
            sidebar.classList.remove('open');
            if (overlay) {
                overlay.classList.remove('open');
            }
            toggle.setAttribute('aria-expanded', 'false');
        }

        toggle.addEventListener('click', function () {
            var open = sidebar.classList.toggle('open');
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
        document.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape') {
                closeSidebar();
            }
        });
        // 移动端点击侧栏链接后自动收起
        var links = sidebar.querySelectorAll('a');
        for (var i = 0; i < links.length; i++) {
            links[i].addEventListener('click', function () {
                if (window.innerWidth <= 768) {
                    closeSidebar();
                }
            });
        }
    }

    /**
     * 危险操作表单二次确认（.confirm-submit）：文章页 CSP 仅允许 self 脚本，
     * 无法使用内联 onsubmit，故在此统一委托处理
     */
    function initConfirmForms() {
        document.addEventListener('submit', function (ev) {
            var form = ev.target;
            if (form.classList && form.classList.contains('confirm-submit')) {
                var msg = form.getAttribute('data-confirm') || '确认执行该操作？';
                if (!window.confirm(msg)) {
                    ev.preventDefault();
                }
            }
        });
    }

    function init() {
        initDarkMode();
        initSidebar();
        initConfirmForms();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
