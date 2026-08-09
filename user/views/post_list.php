<?php
/**
 * 后台视图：文章列表
 */
defined('APP_BOOT') or exit;
$tabs = array('all' => '全部', 'published' => '已发布', 'pending' => '待审核', 'draft' => '草稿', 'trash' => '回收站');
$statusTags = array(
    'published' => '<span class="tag green">已发布</span>',
    'pending'   => '<span class="tag yellow">待审核</span>',
    'draft'     => '<span class="tag gray">草稿</span>',
    'trash'     => '<span class="tag red">回收站</span>',
);
?>
<div class="card">
    <div class="form-inline" style="margin-bottom:14px">
        <?php foreach ($tabs as $tabKey => $tabName): ?>
        <a class="btn small <?php echo $status === $tabKey ? '' : 'gray'; ?>"
           href="<?php echo e(site_base_admin('post/list&status=' . $tabKey)); ?>"><?php echo e($tabName); ?></a>
        <?php endforeach; ?>
        <?php if (Auth::check_cap('edit_posts')): ?>
        <a class="btn small green" style="margin-left:auto" href="<?php echo e(site_base_admin('post/edit')); ?>">＋ 新增文章</a>
        <?php endif; ?>
    </div>
    <div class="table-wrap">
    <table class="table">
        <tr><th>ID</th><th>标题</th><th>作者</th><th>分类</th><th>状态</th><th>浏览</th><th>更新时间</th><th>操作</th></tr>
        <?php if (empty($posts)): ?>
        <tr><td colspan="8" style="text-align:center;color:#999">暂无数据</td></tr>
        <?php endif; ?>
        <?php foreach ($posts as $postItem): ?>
        <tr>
            <td><?php echo (int) $postItem['id']; ?></td>
            <td><?php echo e($postItem['title']); ?><?php if ((int) $postItem['is_page'] === 1): ?> <span class="tag gray">页面</span><?php endif; ?><?php if (!empty($postItem['is_top'])): ?> <span class="tag green">置顶</span><?php endif; ?></td>
            <td><?php echo isset($users[(int) $postItem['author_id']]) ? e($users[(int) $postItem['author_id']]) : '-'; ?></td>
            <td><?php echo isset($cats[(int) $postItem['category_id']]) ? e($cats[(int) $postItem['category_id']]) : '-'; ?></td>
            <td><?php echo $statusTags[$postItem['status']]; ?></td>
            <td><?php echo (int) $postItem['views']; ?></td>
            <td><?php echo e($postItem['updated_at']); ?></td>
            <td>
                <?php if ($postItem['status'] === 'pending' && Auth::check_cap('moderate_posts')): ?>
                <form method="post" action="<?php echo e(site_base_admin('post/audit')); ?>" style="display:inline">
                    <?php echo Csrf::field(); ?>
                    <input type="hidden" name="id" value="<?php echo (int) $postItem['id']; ?>">
                    <button class="btn small green" name="decision" value="approve">通过</button>
                    <button class="btn small gray" name="decision" value="reject">驳回</button>
                </form>
                <?php endif; ?>
                <?php if ($postItem['status'] !== 'trash'): ?>
                <a class="btn small" href="<?php echo e(site_base_admin('post/edit&id=' . (int) $postItem['id'])); ?>">编辑</a>
                <?php endif; ?>
                <?php if ($postItem['status'] === 'published' && Auth::check_cap('moderate_posts')): ?>
                <form method="post" action="<?php echo e(site_base_admin('post/top')); ?>" style="display:inline">
                    <?php echo Csrf::field(); ?>
                    <input type="hidden" name="id" value="<?php echo (int) $postItem['id']; ?>">
                    <input type="hidden" name="status" value="<?php echo e($status); ?>">
                    <button class="btn small <?php echo empty($postItem['is_top']) ? 'gray' : ''; ?>" type="submit"><?php echo empty($postItem['is_top']) ? '置顶' : '取消置顶'; ?></button>
                </form>
                <?php endif; ?>
                <?php if (Auth::check_cap('delete_posts')): ?>
                    <?php if ($postItem['status'] === 'trash'): ?>
                    <form method="post" action="<?php echo e(site_base_admin('post/restore')); ?>" style="display:inline">
                        <?php echo Csrf::field(); ?>
                        <input type="hidden" name="id" value="<?php echo (int) $postItem['id']; ?>">
                        <button class="btn small">恢复</button>
                    </form>
                    <form method="post" action="<?php echo e(site_base_admin('post/destroy')); ?>" style="display:inline"
                          onsubmit="return confirm('彻底删除后不可恢复，确认？')">
                        <?php echo Csrf::field(); ?>
                        <input type="hidden" name="id" value="<?php echo (int) $postItem['id']; ?>">
                        <button class="btn small red">彻底删除</button>
                    </form>
                    <?php else: ?>
                    <form method="post" action="<?php echo e(site_base_admin('post/delete')); ?>" style="display:inline"
                          onsubmit="return confirm('确认移入回收站？')">
                        <?php echo Csrf::field(); ?>
                        <input type="hidden" name="id" value="<?php echo (int) $postItem['id']; ?>">
                        <button class="btn small red">删除</button>
                    </form>
                    <?php endif; ?>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    </div>
    <?php echo admin_pager($page, $totalPages, 'post/list&status=' . $status . '&'); ?>
</div>
