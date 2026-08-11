/**
 * 后台公共交互（layui 版）：
 * 1. 明暗模式切换（与前台默认主题共用 cb-darkmode 键，localStorage 记忆，默认跟随系统）
 * 2. 移动端 ☰ 抽屉式导航（固定侧栏滑入滑出 + 遮罩）
 * 3. layui 模块初始化：导航/表单控件渲染、data-confirm 弹层确认、闪存消息轻提示
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
        // 图标随状态切换：暗色下显示太阳（点击回亮色），亮色下显示月亮
        var icon = document.getElementById('admin-darkmode-icon');
        if (icon) {
            icon.classList.remove('layui-icon-moon', 'layui-icon-light');
            icon.classList.add(enable ? 'layui-icon-light' : 'layui-icon-moon');
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

    /** data-confirm 表单：提交前经 layer.confirm 二次确认（取代原生 confirm） */
    function initConfirmForms(layer) {
        document.addEventListener('submit', function (e) {
            var form = e.target;
            if (!form || !form.getAttribute) {
                return;
            }
            var msg = form.getAttribute('data-confirm');
            if (!msg) {
                return;
            }
            if (layer) {
                e.preventDefault();
                layer.confirm(msg, { icon: 3, title: '操作确认', btn: ['确认', '取消'] }, function (index) {
                    layer.close(index);
                    form.submit();
                });
            } else {
                // layui 未加载时回退原生确认，功能不受影响
                if (!window.confirm(msg)) {
                    e.preventDefault();
                }
            }
        });
    }

    /** 闪存消息轻提示（DOM 内已有可见块，toast 仅作强化，取第一条避免连弹） */
    function initFlashToast(layer) {
        var msgs = window.CB_FLASH;
        if (!msgs || !msgs.length) {
            return;
        }
        var first = msgs[0];
        layer.msg(first.text, {
            icon: first.type === 'success' ? 1 : 2,
            time: 2500,
            anim: 6
        });
    }

    /** layui 模块初始化：导航/下拉/开关渲染 + 弹层交互 */
    function initLayui() {
        if (!window.layui || !layui.use) {
            // layui 缺失时仅保留原生确认回退
            initConfirmForms(null);
            return;
        }
        layui.use(['element', 'form', 'layer'], function () {
            var layer = layui.layer;
            initConfirmForms(layer);
            initFlashToast(layer);
            // element 模块渲染导航树/选项卡/折叠面板；部分版本自动初始化，此处显式兜底
            if (layui.element && layui.element.render) {
                layui.element.render();
            }
            // form 模块加载时会自动渲染页面上的下拉、开关等，
            // 此处显式再渲染一次，兜底插件钩子在 DOM 就绪后追加的控件
            if (layui.form && layui.form.render) {
                layui.form.render();
            }
            // 暴露给视图脚本（封面/头像上传等）复用
            window.CB_ADMIN = { layer: layer, form: layui.form };
        });
    }

    function init() {
        initDarkMode();
        initSidebar();
        initLayui();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
