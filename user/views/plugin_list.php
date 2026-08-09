<?php
/**
 * 后台视图：插件管理
 */
defined('APP_BOOT') or exit;
?>
<div class="card">
    <div class="table-wrap">
    <table class="table">
        <tr><th>插件</th><th>描述</th><th>版本</th><th>作者</th><th>状态</th><th>操作</th></tr>
        <?php if (empty($plugins)): ?>
        <tr><td colspan="6" style="text-align:center;color:#999">未发现任何插件</td></tr>
        <?php endif; ?>
        <?php foreach ($plugins as $slug => $meta): ?>
        <?php $active = in_array($slug, $actives, true); ?>
        <tr>
            <td><?php echo e($meta['name']); ?><br><small><?php echo e($slug); ?></small></td>
            <td><?php echo e($meta['description']); ?></td>
            <td><?php echo e($meta['version']); ?></td>
            <td><?php echo e($meta['author']); ?></td>
            <td><?php echo $active ? '<span class="tag green">已启用</span>' : '<span class="tag">已禁用</span>'; ?></td>
            <td>
                <?php if ($active): ?>
                <form method="post" action="<?php echo e(site_base_admin('plugin/deactivate')); ?>" style="display:inline">
                    <?php echo Csrf::field(); ?>
                    <input type="hidden" name="slug" value="<?php echo e($slug); ?>">
                    <button class="btn small">禁用</button>
                </form>
                <?php else: ?>
                <form method="post" action="<?php echo e(site_base_admin('plugin/activate')); ?>" style="display:inline">
                    <?php echo Csrf::field(); ?>
                    <input type="hidden" name="slug" value="<?php echo e($slug); ?>">
                    <button class="btn small green">启用</button>
                </form>
                <?php endif; ?>
                <form method="post" action="<?php echo e(site_base_admin('plugin/uninstall')); ?>" style="display:inline"
                      onsubmit="return confirm('确认删除该插件？此操作不可恢复')">
                    <?php echo Csrf::field(); ?>
                    <input type="hidden" name="slug" value="<?php echo e($slug); ?>">
                    <button class="btn small red">删除</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    </div>
</div>
