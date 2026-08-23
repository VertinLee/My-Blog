<?php
/**
 * 后台视图：用户列表（layui 表格/徽章/按钮）
 */
defined('APP_BOOT') or exit;
$roleNames = array(
    'admin'  => admin_t('admin.user.role_admin'),
    'editor' => admin_t('admin.user.role_editor'),
    'user'   => admin_t('admin.user.role_user'),
);
?>
<div class="card">
    <div style="margin-bottom:14px">
        <a class="layui-btn" href="<?php echo e(site_base_admin('user/edit')); ?>">
            <i class="layui-icon layui-icon-add-1"></i> <?php echo e(admin_t('admin.user.add')); ?>
        </a>
    </div>
    <div class="table-wrap">
    <table class="layui-table" lay-skin="line">
        <thead>
            <tr><th>ID</th><th><?php echo e(admin_t('admin.user.col_username')); ?></th><th><?php echo e(admin_t('admin.user.col_nickname')); ?></th><th><?php echo e(admin_t('admin.user.col_email')); ?></th><th><?php echo e(admin_t('admin.user.col_phone')); ?></th><th><?php echo e(admin_t('admin.user.col_role')); ?></th><th><?php echo e(admin_t('admin.common.status')); ?></th><th><?php echo e(admin_t('admin.user.col_locked')); ?></th><th><?php echo e(admin_t('admin.user.col_registered')); ?></th><th><?php echo e(admin_t('admin.common.actions')); ?></th></tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
        <?php $locked = !empty($u['locked_until']) && strtotime($u['locked_until']) > time(); ?>
        <tr>
            <td><?php echo (int) $u['id']; ?></td>
            <td><?php echo e($u['username']); ?><?php echo (int) $u['id'] === Auth::id() ? e(admin_t('admin.user.me')) : ''; ?></td>
            <td><?php echo e($u['nickname']); ?></td>
            <td><?php echo $u['email'] !== null ? e(mask_email($u['email'])) : '-'; ?></td>
            <td><?php echo $u['phone'] !== '' ? e(mask_phone($u['phone'])) : '-'; ?></td>
            <td><span class="layui-badge-rim layui-badge"><?php echo isset($roleNames[$u['role']]) ? e($roleNames[$u['role']]) : e($u['role']); ?></span></td>
            <td>
                <?php if ((int) $u['status'] === 1): ?><span class="layui-badge layui-bg-green"><?php echo e(admin_t('admin.user.status_ok')); ?></span>
                <?php else: ?><span class="layui-badge layui-bg-red"><?php echo e(admin_t('admin.user.status_disabled')); ?></span><?php endif; ?>
                <?php if (!empty($u['is_banned'])): ?><span class="layui-badge layui-bg-red"><?php echo e(admin_t('admin.user.status_banned')); ?></span><?php endif; ?>
                <?php if (!empty($u['is_deleted'])): ?><span class="layui-badge layui-bg-gray"><?php echo e(admin_t('admin.user.status_deleted')); ?></span><?php endif; ?>
            </td>
            <td><?php echo $locked ? '<span class="layui-badge layui-bg-red">' . e(admin_t('admin.user.locked_until', array($u['locked_until']))) . '</span>' : '-'; ?></td>
            <td><?php echo e($u['created_at']); ?></td>
            <td>
                <div class="row-actions">
                <a class="layui-btn layui-btn-xs" href="<?php echo e(site_base_admin('user/edit&id=' . (int) $u['id'])); ?>"><?php echo e(admin_t('admin.common.edit')); ?></a>
                <?php if ($locked): ?>
                <form method="post" action="<?php echo e(site_base_admin('user/unlock')); ?>">
                    <?php echo Csrf::field(); ?>
                    <input type="hidden" name="id" value="<?php echo (int) $u['id']; ?>">
                    <button class="layui-btn layui-btn-xs layui-btn-normal"><?php echo e(admin_t('admin.user.unlock')); ?></button>
                </form>
                <?php endif; ?>
                <?php // 自己与安装管理员不可封禁/注销（服务端同样拦截，此处仅隐藏按钮） ?>
                <?php if ((int) $u['id'] !== Auth::id() && (int) $u['id'] !== $rootId): ?>
                <?php if (empty($u['is_banned'])): ?>
                <form method="post" action="<?php echo e(site_base_admin('user/ban')); ?>"
                      data-confirm="<?php echo e(admin_t('admin.user.ban_confirm')); ?>">
                    <?php echo Csrf::field(); ?>
                    <input type="hidden" name="id" value="<?php echo (int) $u['id']; ?>">
                    <button class="layui-btn layui-btn-xs layui-btn-primary"><?php echo e(admin_t('admin.user.ban')); ?></button>
                </form>
                <?php else: ?>
                <form method="post" action="<?php echo e(site_base_admin('user/unban')); ?>">
                    <?php echo Csrf::field(); ?>
                    <input type="hidden" name="id" value="<?php echo (int) $u['id']; ?>">
                    <button class="layui-btn layui-btn-xs layui-btn-normal"><?php echo e(admin_t('admin.user.unban')); ?></button>
                </form>
                <?php endif; ?>
                <?php if (empty($u['is_deleted'])): ?>
                <form method="post" action="<?php echo e(site_base_admin('user/deregister')); ?>"
                      data-confirm="<?php echo e(admin_t('admin.user.deregister_confirm')); ?>">
                    <?php echo Csrf::field(); ?>
                    <input type="hidden" name="id" value="<?php echo (int) $u['id']; ?>">
                    <button class="layui-btn layui-btn-xs layui-btn-primary"><?php echo e(admin_t('admin.user.deregister')); ?></button>
                </form>
                <?php else: ?>
                <form method="post" action="<?php echo e(site_base_admin('user/restore')); ?>">
                    <?php echo Csrf::field(); ?>
                    <input type="hidden" name="id" value="<?php echo (int) $u['id']; ?>">
                    <button class="layui-btn layui-btn-xs layui-btn-normal"><?php echo e(admin_t('admin.post.restore')); ?></button>
                </form>
                <?php endif; ?>
                <?php endif; ?>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php echo admin_pager($page, $totalPages, 'user/list&'); ?>
</div>
