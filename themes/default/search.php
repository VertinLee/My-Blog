<?php
/**
 * 默认主题：搜索结果页（页头卡片 + post-card 列表）
 */
defined('APP_BOOT') or exit;
Theme::part('header');
?>
<div id="content">
    <div class="page-header">
        <h1 class="page-title">搜索<?php echo $kw !== '' ? '：' . e($kw) : ''; ?></h1>
        <p class="page-description">共找到 <?php echo (int) $total; ?> 篇相关文章</p>
    </div>

    <?php if (empty($posts)): ?>
    <p class="empty-tip">没有找到相关文章。</p>
    <?php endif; ?>

    <?php foreach ($posts as $postItem): ?>
    <?php $postUrl = Router::url('post', array('slug' => $postItem['slug'] !== '' ? $postItem['slug'] : (int) $postItem['id'], 'id' => (int) $postItem['id'])); ?>
    <?php do_action('post_card_before', $postItem, 'search'); /* 插件注入点：卡片前 */ ?>
    <article class="post-card">
        <h2 class="post-card-title"><?php if (!empty($postItem['is_top'])): ?><span class="badge">置顶</span> <?php endif; ?><a href="<?php echo e($postUrl); ?>"><?php echo e($postItem['title']); ?></a></h2>
        <div class="post-card-meta">
            <?php // 作者名跳转作者页；id=0（“未知作者”回退数据）无页可跳仍用纯文本 ?>
            <?php if ((int) $postItem['author']['id'] > 0): ?>
            <a href="<?php echo e(Router::url('author', array('id' => (int) $postItem['author']['id']))); ?>"><?php echo e($postItem['author']['nickname']); ?></a>
            <?php else: ?>
            <span><?php echo e($postItem['author']['nickname']); ?></span>
            <?php endif; ?>
            <span class="meta-divider">·</span>
            <span><?php echo e(date_fmt($postItem['created_at'])); ?></span>
        </div>
        <p class="post-card-excerpt"><?php echo e(!empty($postItem['excerpt']) ? $postItem['excerpt'] : excerpt_of($postItem['content'])); ?></p>
        <a class="post-card-readmore" href="<?php echo e($postUrl); ?>">继续阅读</a>
    </article>
    <?php do_action('post_card_after', $postItem, 'search'); /* 插件注入点：卡片后 */ ?>
    <?php endforeach; ?>

    <?php if ($kw !== '' && ($page > 1 || $page < $totalPages)): ?>
    <nav class="pagination">
        <?php // 搜索分页需携带关键词 ?>
        <?php if ($page > 1): ?>
        <a class="page-link" href="<?php echo e(Router::url('search', array('q' => $kw)) . '&page=' . ($page - 1)); ?>">« 上一页</a>
        <?php endif; ?>
        <?php if ($page < $totalPages): ?>
        <a class="page-link" href="<?php echo e(Router::url('search', array('q' => $kw)) . '&page=' . ($page + 1)); ?>">下一页 »</a>
        <?php endif; ?>
    </nav>
    <?php endif; ?>
<?php Theme::part('footer'); ?>
