<?php
/**
 * 默认主题：404 页面
 */
defined('APP_BOOT') or exit;
Theme::part('header');
?>
<div id="content">
    <div class="error-content">
        <div class="error-code">404</div>
        <p class="error-message">页面不存在或已被移除。</p>
        <p><a class="post-card-readmore" href="<?php echo e(Router::url('home')); ?>">返回首页</a></p>
    </div>
<?php Theme::part('footer'); ?>
