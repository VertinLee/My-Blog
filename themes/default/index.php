<?php
/**
 * 默认主题：首页（hendrix 式 banner + post-card 文章列表）
 */
defined('APP_BOOT') or exit;
Theme::part('header');
?>
<div class="banner">
    <div class="banner-content">
        <h1 class="banner-title"><?php echo e(site_name()); ?></h1>
        <?php if (site_motto() !== ''): ?>
        <p class="banner-subtitle"><?php echo e(site_motto()); ?></p>
        <?php endif; ?>
    </div>
</div>

<div id="content">
    <?php foreach (flash_pull() as $fm): ?>
    <div class="<?php echo $fm['type'] === 'success' ? 'form-ok' : 'form-error'; ?>"><?php echo e($fm['text']); ?></div>
    <?php endforeach; ?>

    <?php if (empty($posts)): ?>
    <p class="empty-tip">暂无文章。</p>
    <?php endif; ?>

    <?php foreach ($posts as $postItem): ?>
    <?php $postUrl = Router::url('post', array('slug' => $postItem['slug'] !== '' ? $postItem['slug'] : (int) $postItem['id'], 'id' => (int) $postItem['id'])); ?>
    <?php do_action('post_card_before', $postItem, 'index'); /* 插件注入点：卡片前 */ ?>
    <article class="post-card">
        <h2 class="post-card-title"><?php if (!empty($postItem['is_top'])): ?><span class="badge">置顶</span> <?php endif; ?><a href="<?php echo e($postUrl); ?>"><?php echo e($postItem['title']); ?></a></h2>
        <div class="post-card-meta">
            <a href="<?php echo e(Router::url('author', array('id' => (int) $postItem['author_id']))); ?>"><?php echo e($postItem['author']['nickname']); ?></a>
            <span class="meta-divider">·</span>
            <span><?php echo e(date_fmt($postItem['created_at'])); ?></span>
            <?php if (!empty($postItem['category'])): ?>
            <span class="meta-divider">·</span>
            <a href="<?php echo e(Router::url('category', array('slug' => $postItem['category']['slug']))); ?>"><?php echo e($postItem['category']['name']); ?></a>
            <?php endif; ?>
            <span class="meta-divider">·</span>
            <span>阅读 <?php echo (int) $postItem['views']; ?></span>
        </div>
        <p class="post-card-excerpt"><?php echo e(!empty($postItem['excerpt']) ? $postItem['excerpt'] : excerpt_of($postItem['content'])); ?></p>
        <a class="post-card-readmore" href="<?php echo e($postUrl); ?>">继续阅读</a>
    </article>
    <?php do_action('post_card_after', $postItem, 'index'); /* 插件注入点：卡片后 */ ?>
    <?php endforeach; ?>

    <?php echo paginate($page, $totalPages, $route, $routeParams); ?>
<?php Theme::part('footer'); ?>
