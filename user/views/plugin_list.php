<?php
/**
 * 后台视图：插件管理（layui 表格/徽章/按钮）
 */
defined('APP_BOOT') or exit;
?>
<div class="card">
    <h3>上传插件（zip，包内须含 {slug}.php 主文件并声明 Plugin Name）</h3>
    <form method="post" action="<?php echo e(site_base_admin('plugin/upload')); ?>" enctype="multipart/form-data" class="filter-bar">
        <?php echo Csrf::field(); ?>
        <input type="file" name="plugin_zip" accept=".zip" required>
        <button class="layui-btn" type="submit"><i class="layui-icon layui-icon-upload"></i> 上传</button>
    </form>
    <p class="tip">同名插件将执行覆盖更新（旧目录自动备份、失败回滚）；服务器上传上限约 <?php echo (int) floor($uploadLimit / 1048576); ?>MB，程序限制 10MB。</p>
</div>
<?php if (!empty($orphans)): ?>
<div class="card">
    <p class="tip"><?php echo e(admin_t('admin.plugin.orphan_tip')); ?></p>
    <div class="row-actions">
    <?php foreach ($orphans as $orphanSlug): ?>
    <form method="post" action="<?php echo e(site_base_admin('plugin/cleanup_orphan')); ?>"
          data-confirm="<?php echo e(admin_t('admin.plugin.orphan_confirm')); ?>">
        <?php echo Csrf::field(); ?>
        <input type="hidden" name="slug" value="<?php echo e($orphanSlug); ?>">
        <span><?php echo e($orphanSlug); ?></span>
        <button class="layui-btn layui-btn-xs layui-btn-danger"><?php echo e(admin_t('admin.plugin.orphan_btn')); ?></button>
    </form>
    <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
<div class="card">
    <div class="table-wrap">
    <table class="layui-table" lay-skin="line">
        <thead>
            <tr><th><?php echo e(admin_t('admin.plugin.col_name')); ?></th><th><?php echo e(admin_t('admin.category.description')); ?></th><th><?php echo e(admin_t('admin.theme.col_version')); ?></th><th><?php echo e(admin_t('admin.post.col_author')); ?></th><th><?php echo e(admin_t('admin.common.status')); ?></th><th><?php echo e(admin_t('admin.common.actions')); ?></th></tr>
        </thead>
        <tbody>
        <?php if (empty($plugins)): ?>
        <tr class="empty-row"><td colspan="6"><?php echo e(admin_t('admin.plugin.empty')); ?></td></tr>
        <?php endif; ?>
        <?php foreach ($plugins as $slug => $meta): ?>
        <?php $active = in_array($slug, $actives, true); ?>
        <tr>
            <td><?php echo e($meta['name']); ?><br><small><?php echo e($slug); ?></small></td>
            <td><?php echo e($meta['description']); ?></td>
            <td><?php echo e($meta['version']); ?></td>
            <td><?php echo e($meta['author']); ?></td>
            <td><?php echo $active ? '<span class="layui-badge layui-bg-green">' . e(admin_t('admin.plugin.active')) . '</span>' : '<span class="layui-badge layui-bg-gray">' . e(admin_t('admin.plugin.inactive')) . '</span>'; ?></td>
            <td>
                <div class="row-actions">
                <?php if ($active): ?>
                <form method="post" action="<?php echo e(site_base_admin('plugin/deactivate')); ?>">
                    <?php echo Csrf::field(); ?>
                    <input type="hidden" name="slug" value="<?php echo e($slug); ?>">
                    <button class="layui-btn layui-btn-xs layui-btn-primary"><?php echo e(admin_t('admin.common.disabled')); ?></button>
                </form>
                <?php else: ?>
                <form method="post" action="<?php echo e(site_base_admin('plugin/activate')); ?>">
                    <?php echo Csrf::field(); ?>
                    <input type="hidden" name="slug" value="<?php echo e($slug); ?>">
                    <button class="layui-btn layui-btn-xs layui-btn-normal"><?php echo e(admin_t('admin.common.enabled')); ?></button>
                </form>
                <?php endif; ?>
                <form method="post" action="<?php echo e(site_base_admin('plugin/uninstall')); ?>"
                      data-confirm="<?php echo e(admin_t('admin.plugin.delete_confirm')); ?>">
                    <?php echo Csrf::field(); ?>
                    <input type="hidden" name="slug" value="<?php echo e($slug); ?>">
                    <button class="layui-btn layui-btn-xs layui-btn-danger"><?php echo e(admin_t('admin.common.delete')); ?></button>
                </form>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
