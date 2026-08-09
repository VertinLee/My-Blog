<?php
/**
 * 默认主题：独立页面（如“关于我”）
 */
defined('APP_BOOT') or exit;
Theme::part('header');
$pagePost = the_post();
?>
<div id="content">
    <article>
        <header class="article-header">
            <h1 class="article-title"><?php echo e($pagePost['title']); ?></h1>
        </header>
        <div class="article-content">
            <?php echo render_content($pagePost['content']); ?>
        </div>
    </article>
<?php Theme::part('footer'); ?>
