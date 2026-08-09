<?php
/**
 * 后台视图：评论列表与审核
 */
defined('APP_BOOT') or exit;
$tabs = array('all' => '全部', 'pending' => '待审核', 'published' => '已公开', 'trash' => '回收站');
?>
<div class="card">
    <div class="form-inline" style="margin-bottom:14px">
        <?php foreach ($tabs as $tabKey => $tabName): ?>
        <a class="btn small <?php echo $status === $tabKey ? '' : 'gray'; ?>"
           href="<?php echo e(site_base_admin('comment/list&status=' . $tabKey)); ?>"><?php echo e($tabName); ?></a>
        <?php endforeach; ?>
    </div>
    <div class="table-wrap">
    <table class="table">
        <tr><th>ID</th><th>作者</th><th>内容</th><th>所属文章</th><th>状态</th><th>时间</th><th>操作</th></tr>
        <?php if (empty($comments)): ?>
        <tr><td colspan="7" style="text-align:center;color:#999">暂无数据</td></tr>
        <?php endif; ?>
        <?php foreach ($comments as $commentItem): ?>
        <tr>
            <td><?php echo (int) $commentItem['id']; ?></td>
            <td><?php echo isset($users[(int) $commentItem['user_id']]) ? e($users[(int) $commentItem['user_id']]) : '-'; ?></td>
            <td style="max-width:340px"><?php echo e(mb_substr($commentItem['content'], 0, 80)); ?></td>
            <td><?php echo isset($posts[(int) $commentItem['post_id']]) ? e($posts[(int) $commentItem['post_id']]) : '-'; ?></td>
            <td>
                <?php if ($commentItem['status'] === 'published'): ?><span class="tag green">已公开</span>
                <?php elseif ($commentItem['status'] === 'pending'): ?><span class="tag yellow">待审核</span>
                <?php else: ?><span class="tag red">回收站</span><?php endif; ?>
            </td>
            <td><?php echo e($commentItem['created_at']); ?></td>
            <td>
                <?php if ($commentItem['status'] === 'pending'): ?>
                <form method="post" action="<?php echo e(site_base_admin('comment/audit')); ?>" style="display:inline">
                    <?php echo Csrf::field(); ?>
                    <input type="hidden" name="id" value="<?php echo (int) $commentItem['id']; ?>">
                    <button class="btn small green" name="decision" value="approve">通过</button>
                    <button class="btn small red" name="decision" value="reject">驳回</button>
                </form>
                <?php elseif ($commentItem['status'] === 'published'): ?>
                <form method="post" action="<?php echo e(site_base_admin('comment/delete')); ?>" style="display:inline">
                    <?php echo Csrf::field(); ?>
                    <input type="hidden" name="id" value="<?php echo (int) $commentItem['id']; ?>">
                    <button class="btn small red">删除</button>
                </form>
                <?php else: ?>
                <form method="post" action="<?php echo e(site_base_admin('comment/restore')); ?>" style="display:inline">
                    <?php echo Csrf::field(); ?>
                    <input type="hidden" name="id" value="<?php echo (int) $commentItem['id']; ?>">
                    <button class="btn small">恢复</button>
                </form>
                <?php endif; ?>
                <?php do_action('comment_list_row_actions', $commentItem); /* 插件注入点：行内追加操作按钮（输出需自带 CSRF 表单并自行转义） */ ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    </div>
    <?php echo admin_pager($page, $totalPages, 'comment/list&status=' . $status . '&'); ?>
</div>
