<?php
/**
 * 后台视图：分类管理
 */
defined('APP_BOOT') or exit;
?>
<div class="card">
    <h3>新增分类</h3>
    <form method="post" action="<?php echo e(site_base_admin('category/save')); ?>" class="form-inline">
        <?php echo Csrf::field(); ?>
        <input type="hidden" name="id" value="0">
        <div class="form-row" style="margin:0"><label>名称</label><input type="text" name="name" required></div>
        <div class="form-row" style="margin:0"><label>中文释义（slug）</label><input type="text" name="slug" pattern="[a-z0-9-]+" required></div>
        <div class="form-row" style="margin:0"><label>描述</label><input type="text" name="description"></div>
        <div class="form-row" style="margin:0"><label>排序</label><input type="number" name="sort" value="0" style="width:80px"></div>
        <button class="btn" type="submit">添加</button>
    </form>
</div>

<div class="card">
    <div class="table-wrap">
    <table class="table">
        <tr><th>ID</th><th>名称</th><th>中文释义（slug）</th><th>描述</th><th>排序</th><th>操作</th></tr>
        <?php foreach ($categories as $cat): ?>
        <tr>
            <td><?php echo (int) $cat['id']; ?></td>
            <td>
                <form method="post" action="<?php echo e(site_base_admin('category/save')); ?>" class="form-inline">
                    <?php echo Csrf::field(); ?>
                    <input type="hidden" name="id" value="<?php echo (int) $cat['id']; ?>">
                    <input type="text" name="name" value="<?php echo e($cat['name']); ?>" style="width:120px" required>
                    <input type="text" name="slug" value="<?php echo e($cat['slug']); ?>" style="width:120px" pattern="[a-z0-9-]+" required>
                    <input type="text" name="description" value="<?php echo e($cat['description']); ?>" style="width:160px">
                    <input type="number" name="sort" value="<?php echo (int) $cat['sort']; ?>" style="width:70px">
                    <button class="btn small" type="submit">保存</button>
                </form>
            </td>
            <td><?php echo e($cat['slug']); ?></td>
            <td><?php echo e(mb_substr($cat['description'], 0, 30)); ?></td>
            <td><?php echo (int) $cat['sort']; ?></td>
            <td>
                <form method="post" action="<?php echo e(site_base_admin('category/delete')); ?>"
                      onsubmit="return confirm('确认删除该分类？')">
                    <?php echo Csrf::field(); ?>
                    <input type="hidden" name="id" value="<?php echo (int) $cat['id']; ?>">
                    <button class="btn small red">删除</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    </div>
</div>
