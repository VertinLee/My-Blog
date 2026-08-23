<?php
/**
 * 后台视图：导航管理（自定义导航项增删改，支持一层父子层级与拖拽排序）
 * JS 下按 DOM 顺序重编号提交 title_i/url_i/parent_i + row_total；
 * 无 JS 时按服务端渲染的 name（旧顺序）原样提交，兜底新增行在列表底部
 * 注：父级下拉为原生 select（行模板由 JS 动态克隆，不经 layui form 渲染）
 */
defined('APP_BOOT') or exit;
$flat = AdminSetting::flattenNav($items);
// 顶层行号集合（父级下拉仅允许选择顶层行，保证单层结构）
$topIndexes = array();
foreach ($flat as $i => $row) {
    if ($row['parent'] < 0) {
        $topIndexes[] = $i;
    }
}
?>
<div class="card">
    <h3><?php echo e(admin_t('admin.menu.setting_nav')); ?></h3>
    <p class="tip">
        <?php echo admin_t('admin.nav.help'); ?>
    </p>
    <form id="nav-form" method="post" action="<?php echo e(site_base_admin('setting/nav_save')); ?>"
          data-max-tip="<?php echo e(admin_t('admin.nav.max_tip')); ?>">
        <?php echo Csrf::field(); ?>
        <input type="hidden" id="nav-row-total" name="row_total" value="-1">
        <ul id="nav-list" class="nav-list">
            <?php foreach ($flat as $i => $row): ?>
            <li class="nav-row<?php echo $row['parent'] >= 0 ? ' nav-child' : ''; ?>" data-key="<?php echo (int) $i; ?>">
                <span class="nav-handle" title="<?php echo e(admin_t('admin.nav.drag')); ?>"><i class="layui-icon layui-icon-slider"></i></span>
                <input type="text" class="layui-input nav-title" data-field="title" name="title_<?php echo (int) $i; ?>"
                    value="<?php echo e($row['title']); ?>" placeholder="<?php echo e(admin_t('admin.post.col_title')); ?>" required>
                <input type="text" class="layui-input nav-url" data-field="url" name="url_<?php echo (int) $i; ?>"
                    value="<?php echo e($row['url']); ?>"
                    placeholder="<?php echo e($row['parent'] >= 0 ? admin_t('admin.nav.child_url_required') : admin_t('admin.nav.top_url_optional')); ?>">
                <select class="layui-input nav-parent" data-field="parent" name="parent_<?php echo (int) $i; ?>">
                    <option value="-1"<?php echo $row['parent'] < 0 ? ' selected' : ''; ?>><?php echo e(admin_t('admin.nav.top')); ?></option>
                    <?php foreach ($topIndexes as $t): ?>
                    <?php if ($t >= $i) { break; } ?>
                    <option value="<?php echo (int) $t; ?>"<?php echo $row['parent'] === $t ? ' selected' : ''; ?>><?php echo e(mb_substr($flat[$t]['title'], 0, 12)); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="layui-btn layui-btn-xs layui-btn-danger nav-del" title="<?php echo e(admin_t('admin.nav.remove_row')); ?>"><i class="layui-icon layui-icon-close"></i></button>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php if (empty($flat)): ?>
        <p class="tip"><?php echo e(admin_t('admin.nav.empty')); ?></p>
        <?php endif; ?>

        <?php // 无 JS 兜底新增行（恒为顶层）；JS 启用后隐藏，改由「添加导航项」按钮追加可选父级的完整行 ?>
        <div id="nav-new-row" class="nav-new-fallback">
            <input type="text" name="new_title" placeholder="<?php echo e(admin_t('admin.nav.new_title')); ?>" class="layui-input">
            <input type="text" name="new_url" placeholder="<?php echo e(admin_t('admin.nav.new_url')); ?>" class="layui-input">
            <span class="tip"><?php echo e(admin_t('admin.nav.new_badge')); ?></span>
        </div>

        <div class="nav-actions">
            <button type="button" id="nav-add-btn" class="layui-btn layui-btn-primary" style="display:none">
                <i class="layui-icon layui-icon-add-1"></i> <?php echo e(admin_t('admin.nav.add_btn')); ?>
            </button>
            <button class="layui-btn" type="submit"><i class="layui-icon layui-icon-ok"></i> <?php echo e(admin_t('admin.common.save')); ?></button>
        </div>

        <?php // JS 动态新增行模板：父级选项为当前全部顶层行（已有顶层项时添加即可直接选父级）；
              // fresh 隐藏域供服务端识别新增行（新增行链接必填） ?>
        <template id="nav-row-tpl">
        <li class="nav-row" data-key="">
            <span class="nav-handle" title="<?php echo e(admin_t('admin.nav.drag')); ?>"><i class="layui-icon layui-icon-slider"></i></span>
            <input type="text" class="layui-input nav-title" data-field="title" value="" placeholder="<?php echo e(admin_t('admin.post.col_title')); ?>" required>
            <input type="text" class="layui-input nav-url" data-field="url" value="" placeholder="<?php echo e(admin_t('admin.nav.new_url_required')); ?>" required>
            <input type="hidden" data-field="fresh" value="1">
            <select class="layui-input nav-parent" data-field="parent">
                <option value="-1" selected><?php echo e(admin_t('admin.nav.top')); ?></option>
                <?php foreach ($topIndexes as $t): ?>
                <option value="<?php echo (int) $t; ?>"><?php echo e(mb_substr($flat[$t]['title'], 0, 12)); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="layui-btn layui-btn-xs layui-btn-danger nav-del" title="<?php echo e(admin_t('admin.nav.remove_row')); ?>"><i class="layui-icon layui-icon-close"></i></button>
        </li>
        </template>
    </form>
</div>
<script src="<?php echo e(assets_url('admin/nav_sort.js')); ?>"></script>
