<?php
/**
 * 后台视图：分类管理（layui 表单 + 可编辑表格）
 */
defined('APP_BOOT') or exit;
?>
<div class="card">
    <h3><?php echo e(admin_t('admin.category.add')); ?></h3>
    <form method="post" action="<?php echo e(site_base_admin('category/save')); ?>" class="layui-form filter-bar">
        <?php echo Csrf::field(); ?>
        <input type="hidden" name="id" value="0">
        <input type="text" name="name" placeholder="<?php echo e(admin_t('admin.category.name')); ?>" required class="layui-input" style="width:130px">
        <input type="text" name="slug" placeholder="<?php echo e(admin_t('admin.category.slug')); ?>" pattern="[a-z0-9-]+" required class="layui-input" style="width:160px">
        <input type="text" name="description" placeholder="<?php echo e(admin_t('admin.category.description')); ?>" class="layui-input" style="width:180px">
        <input type="number" name="sort" value="0" class="layui-input" style="width:80px" title="<?php echo e(admin_t('admin.category.sort')); ?>">
        <button class="layui-btn" type="submit"><i class="layui-icon layui-icon-add-1"></i> <?php echo e(admin_t('admin.category.add_btn')); ?></button>
    </form>
</div>

<div class="card">
    <div class="table-wrap">
    <table class="layui-table" lay-skin="line">
        <thead>
            <tr><th>ID</th><th><?php echo e(admin_t('admin.category.col_edit')); ?></th><th><?php echo e(admin_t('admin.category.slug')); ?></th><th><?php echo e(admin_t('admin.category.description')); ?></th><th><?php echo e(admin_t('admin.category.sort')); ?></th><th><?php echo e(admin_t('admin.common.actions')); ?></th></tr>
        </thead>
        <tbody>
        <?php foreach ($categories as $cat): ?>
        <tr>
            <td><?php echo (int) $cat['id']; ?></td>
            <td>
                <form method="post" action="<?php echo e(site_base_admin('category/save')); ?>" class="filter-bar">
                    <?php echo Csrf::field(); ?>
                    <input type="hidden" name="id" value="<?php echo (int) $cat['id']; ?>">
                    <input type="text" name="name" value="<?php echo e($cat['name']); ?>" style="width:120px" required class="layui-input">
                    <input type="text" name="slug" value="<?php echo e($cat['slug']); ?>" style="width:120px" pattern="[a-z0-9-]+" required class="layui-input">
                    <input type="text" name="description" value="<?php echo e($cat['description']); ?>" style="width:160px" class="layui-input">
                    <input type="number" name="sort" value="<?php echo (int) $cat['sort']; ?>" style="width:70px" class="layui-input">
                    <button class="layui-btn layui-btn-xs" type="submit"><?php echo e(admin_t('admin.common.save')); ?></button>
                </form>
            </td>
            <td><?php echo e($cat['slug']); ?></td>
            <td><?php echo e(mb_substr($cat['description'], 0, 30)); ?></td>
            <td><?php echo (int) $cat['sort']; ?></td>
            <td>
                <form method="post" action="<?php echo e(site_base_admin('category/delete')); ?>"
                      data-confirm="<?php echo e(admin_t('admin.category.delete_confirm')); ?>">
                    <?php echo Csrf::field(); ?>
                    <input type="hidden" name="id" value="<?php echo (int) $cat['id']; ?>">
                    <button class="layui-btn layui-btn-xs layui-btn-danger"><?php echo e(admin_t('admin.common.delete')); ?></button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
