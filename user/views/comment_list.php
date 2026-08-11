<?php
/**
 * 后台视图：评论列表与审核（layui 选项卡 + 表格/徽章/按钮）
 */
defined('APP_BOOT') or exit;
$tabs = array('all' => '全部', 'pending' => '待审核', 'published' => '已公开', 'trash' => '回收站');
?>
<div class="card">
    <?php // 状态筛选：layui 简明选项卡（点击走整页链接，服务端渲染） ?>
    <div class="layui-tab layui-tab-brief">
        <ul class="layui-tab-title">
            <?php foreach ($tabs as $tabKey => $tabName): ?>
            <li class="<?php echo $status === $tabKey ? 'layui-this' : ''; ?>">
                <a href="<?php echo e(site_base_admin('comment/list&status=' . $tabKey)); ?>"><?php echo e($tabName); ?></a>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <div class="table-wrap">
    <table class="layui-table" lay-skin="line">
        <thead>
            <tr><th>ID</th><th>作者</th><th>内容</th><th>所属文章</th><th>状态</th><th>时间</th><th>操作</th></tr>
        </thead>
        <tbody>
        <?php if (empty($comments)): ?>
        <tr class="empty-row"><td colspan="7">暂无数据</td></tr>
        <?php endif; ?>
        <?php foreach ($comments as $commentItem): ?>
        <tr>
            <td><?php echo (int) $commentItem['id']; ?></td>
            <td><?php echo isset($users[(int) $commentItem['user_id']]) ? e($users[(int) $commentItem['user_id']]) : '-'; ?></td>
            <td style="max-width:340px"><?php echo e(mb_substr($commentItem['content'], 0, 80)); ?></td>
            <td><?php echo isset($posts[(int) $commentItem['post_id']]) ? e($posts[(int) $commentItem['post_id']]) : '-'; ?></td>
            <td>
                <?php if ($commentItem['status'] === 'published'): ?><span class="layui-badge layui-bg-green">已公开</span>
                <?php elseif ($commentItem['status'] === 'pending'): ?><span class="layui-badge layui-bg-warn">待审核</span>
                <?php else: ?><span class="layui-badge layui-bg-red">回收站</span><?php endif; ?>
            </td>
            <td><?php echo e($commentItem['created_at']); ?></td>
            <td>
                <div class="row-actions">
                <?php if ($commentItem['status'] === 'pending'): ?>
                <form method="post" action="<?php echo e(site_base_admin('comment/audit')); ?>">
                    <?php echo Csrf::field(); ?>
                    <input type="hidden" name="id" value="<?php echo (int) $commentItem['id']; ?>">
                    <button class="layui-btn layui-btn-xs layui-btn-normal" name="decision" value="approve">通过</button>
                    <button class="layui-btn layui-btn-xs layui-btn-danger" name="decision" value="reject">驳回</button>
                </form>
                <?php elseif ($commentItem['status'] === 'published'): ?>
                <form method="post" action="<?php echo e(site_base_admin('comment/delete')); ?>">
                    <?php echo Csrf::field(); ?>
                    <input type="hidden" name="id" value="<?php echo (int) $commentItem['id']; ?>">
                    <button class="layui-btn layui-btn-xs layui-btn-danger">删除</button>
                </form>
                <?php else: ?>
                <form method="post" action="<?php echo e(site_base_admin('comment/restore')); ?>">
                    <?php echo Csrf::field(); ?>
                    <input type="hidden" name="id" value="<?php echo (int) $commentItem['id']; ?>">
                    <button class="layui-btn layui-btn-xs layui-btn-primary">恢复</button>
                </form>
                <?php endif; ?>
                <?php do_action('comment_list_row_actions', $commentItem); /* 插件注入点：行内追加操作按钮（输出需自带 CSRF 表单并自行转义） */ ?>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php echo admin_pager($page, $totalPages, 'comment/list&status=' . $status . '&'); ?>
</div>
