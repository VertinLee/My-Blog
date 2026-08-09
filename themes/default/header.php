<?php
/**
 * 默认主题公共页头：hendrix 式骨架（☰ 按钮 + 遮罩 + site-wrapper + 侧栏 + #main 开启）
 */
defined('APP_BOOT') or exit;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<?php theme_head(); ?>
</head>
<body>
<button type="button" class="sidebar-toggle" id="sidebar-toggle" aria-label="切换菜单" aria-expanded="false">
    <span>☰</span>
</button>
<div class="sidebar-overlay" id="sidebar-overlay"></div>

<div class="site-wrapper">
<?php Theme::part('sidebar'); ?>
<div id="main">
