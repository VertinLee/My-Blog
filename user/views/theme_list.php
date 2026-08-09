<?php
/**
 * 后台视图：模板管理
 */
defined('APP_BOOT') or exit;
?>
<div class="card">
    <h3>上传主题（zip，须含 style.css）</h3>
    <form method="post" action="<?php echo e(site_base_admin('theme/upload')); ?>" enctype="multipart/form-data" class="form-inline">
        <?php echo Csrf::field(); ?>
        <input type="file" name="theme_zip" accept=".zip" required>
        <button class="btn" type="submit">上传</button>
    </form>
</div>

<div class="card">
    <div class="table-wrap">
    <table class="table">
        <tr><th>主题</th><th>名称</th><th>作者</th><th>版本</th><th>描述</th><th>状态</th><th>操作</th></tr>
        <?php if (empty($themes)): ?>
        <tr><td colspan="7" style="text-align:center;color:#999">未发现任何主题</td></tr>
        <?php endif; ?>
        <?php foreach ($themes as $themeDir => $meta): ?>
        <tr>
            <td><?php echo e($themeDir); ?></td>
            <td><?php echo e($meta['name']); ?></td>
            <td><?php echo e($meta['author']); ?></td>
            <td><?php echo e($meta['version']); ?></td>
            <td><?php echo e(mb_substr($meta['description'], 0, 40)); ?></td>
            <td>
                <?php if ($themeDir === $active): ?><span class="tag green">启用中</span>
                <?php else: ?><span class="tag">未启用</span><?php endif; ?>
            </td>
            <td>
                <?php // 设置按钮仅对提供 settings.php 清单的主题展示 ?>
                <?php if (!empty(Theme::settingsSchema($themeDir))): ?>
                <a class="btn small gray" href="<?php echo e(site_base_admin('theme/setting&dir=' . rawurlencode($themeDir))); ?>">设置</a>
                <?php endif; ?>
                <?php if ($themeDir !== $active): ?>
                <form method="post" action="<?php echo e(site_base_admin('theme/activate')); ?>" style="display:inline">
                    <?php echo Csrf::field(); ?>
                    <input type="hidden" name="dir" value="<?php echo e($themeDir); ?>">
                    <button class="btn small green">启用</button>
                </form>
                <?php endif; ?>
                <?php if ($themeDir !== 'default' && $themeDir !== $active): ?>
                <form method="post" action="<?php echo e(site_base_admin('theme/delete')); ?>" style="display:inline"
                      onsubmit="return confirm('确认删除该主题？此操作不可恢复')">
                    <?php echo Csrf::field(); ?>
                    <input type="hidden" name="dir" value="<?php echo e($themeDir); ?>">
                    <button class="btn small red">删除</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    </div>
</div>
