<?php
/**
 * Plugin Name: Hello World
 * Description: 插件开发示例：前台页尾输出 + post_content 过滤器 + 后台设置页
 * Version: 1.0.0
 * Author: Blog Team
 * Requires: 1.0
 */
defined('APP_BOOT') or exit;

/** 前台页尾输出一行注释（演示 action 钩子） */
add_action('front_footer', 'hello_world_footer');

function hello_world_footer()
{
    $text = plugin_option('hello-world', 'text', '你好，世界');
    echo '<!-- Hello World: ' . e($text) . ' -->' . "\n";
}

/** 文章正文输出前追加处理（演示 filter 钩子，此处原样返回） */
add_filter('post_content', 'hello_world_content');

function hello_world_content($html)
{
    return $html;
}

/** 注册后台设置页（演示 admin_menu 钩子） */
add_action('admin_menu', 'hello_world_menu');

function hello_world_menu()
{
    register_plugin_page('hello-world', 'Hello World', 'hello_world_page');
}

/** 设置页渲染：保存/展示一段文案 */
function hello_world_page()
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // 后台 POST 已由内核统一校验 CSRF，这里只处理经校验器的输入
        $text = input_text('hw_text', '', 100, 'post');
        plugin_option_update('hello-world', 'text', $text);
        plugin_log('hello-world.save', array('result' => 'success'));
        echo '<p class="tip">已保存。</p>';
    }
    $text = plugin_option('hello-world', 'text', '你好，世界');
    echo '<form method="post">';
    echo '<div class="form-row"><label>页尾文案</label>'
        . '<input type="text" name="hw_text" value="' . e($text) . '"></div>';
    echo '<button class="btn" type="submit">保存</button>';
    echo '</form>';
}
