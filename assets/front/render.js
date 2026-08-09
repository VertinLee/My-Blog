/**
 * 前台渲染：本地化 KaTeX 公式与 highlight.js 代码高亮（资源缺失时静默降级）
 */
(function () {
    'use strict';

    // KaTeX：行内 .tex-inline 与块级 .tex-block
    if (window.katex) {
        var renderTex = function (selector, displayMode) {
            var nodes = document.querySelectorAll(selector);
            for (var i = 0; i < nodes.length; i++) {
                var el = nodes[i];
                // 幂等守卫：已渲染过的容器跳过，防止脚本重复加载导致同一公式被多次渲染
                if (el.getAttribute('data-tex-rendered') === '1') {
                    continue;
                }
                // 嵌套守卫：容器内已有渲染产物（历史数据中的嵌套容器）时跳过外层，
                // 否则会把内层渲染结果的 textContent 当作新公式再渲染一遍
                if (el.querySelector('[data-tex-rendered], .katex')) {
                    continue;
                }
                el.setAttribute('data-tex-rendered', '1');
                try {
                    katex.render(el.textContent, el, {
                        displayMode: displayMode,
                        throwOnError: false
                    });
                } catch (err) {
                    // 公式渲染失败保留原文
                }
            }
        };
        renderTex('.tex-inline', false);
        renderTex('.tex-block', true);
    }

    // highlight.js：围栏代码块（article-content 为当前主题正文容器，
    // post-content 保留兼容其他主题约定）
    if (window.hljs) {
        var codes = document.querySelectorAll('.article-content pre code, .post-content pre code');
        for (var j = 0; j < codes.length; j++) {
            try {
                hljs.highlightElement(codes[j]);
            } catch (err) {
                // 高亮失败保留原样
            }
        }
    }
})();
