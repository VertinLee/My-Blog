<?php
/**
 * 默认主题：分类/作者归档（页头卡片 + post-card 列表）
 */
defined('APP_BOOT') or exit;
Theme::part('header');
?>
<div id="content">
    <div class="page-header">
        <h1 class="page-title"><?php echo e($title); ?></h1>
        <?php if (isset($subject['description']) && $subject['description'] !== ''): ?>
        <p class="page-description"><?php echo e($subject['description']); ?></p>
        <?php endif; ?>
    </div>

    <?php if (empty($posts)): ?>
    <p class="empty-tip">该归档下暂无文章。</p>
    <?php endif; ?>

    <?php foreach ($posts as $postItem): ?>
    <?php $postUrl = Router::url('post', array('slug' => $postItem['slug'] !== '' ? $postItem['slug'] : (int) $postItem['id'], 'id' => (int) $postItem['id'])); ?>
    <article class="post-card">
        <h2 class="post-card-title"><a href="<?php echo e($postUrl); ?>"><?php echo e($postItem['title']); ?></a></h2>
        <div class="post-card-meta">
            <span><?php echo e($postItem['author']['nickname']); ?></span>
            <span class="meta-divider">·</span>
            <span><?php echo e(date_fmt($postItem['created_at'])); ?></span>
            <span class="meta-divider">·</span>
            <span>阅读 <?php echo (int) $postItem['views']; ?></span>
        </div>
        <p class="post-card-excerpt"><?php echo e(!empty($postItem['excerpt']) ? $postItem['excerpt'] : excerpt_of($postItem['content'])); ?></p>
        <a class="post-card-readmore" href="<?php echo e($postUrl); ?>">继续阅读</a>
    </article>
    <?php endforeach; ?>

    <?php echo paginate($page, $totalPages, $route, $routeParams); ?>
<?php Theme::part('footer'); ?>
