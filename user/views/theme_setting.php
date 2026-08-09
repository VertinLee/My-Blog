<?php
/**
 * 后台视图：主题设置（表单由主题 settings.php 清单驱动，内核通用渲染）
 * 清单字段类型：text / textarea / checkbox / select
 */
defined('APP_BOOT') or exit;
?>
<div class="card">
    <h3>主题设置：<?php echo e($name); ?>（<?php echo e($dir); ?>）</h3>
    <?php if (empty($schema)): ?>
    <p class="tip">该主题未提供设置清单（主题目录内无 settings.php），没有可配置项。</p>
    <?php else: ?>
    <form method="post" action="<?php echo e(site_base_admin('theme/setting_save')); ?>">
        <?php echo Csrf::field(); ?>
        <input type="hidden" name="dir" value="<?php echo e($dir); ?>">
        <?php foreach ($schema as $setKey => $field): ?>
        <?php $cur = isset($settings[$setKey]) ? (string) $settings[$setKey] : $field['default']; ?>
        <div class="form-row">
            <?php if ($field['type'] === 'checkbox'): ?>
            <label style="display:flex;gap:6px;align-items:center">
                <input type="checkbox" name="<?php echo e($setKey); ?>" value="1" <?php echo $cur === '1' ? 'checked' : ''; ?>>
                <?php echo e($field['label']); ?>
            </label>
            <?php elseif ($field['type'] === 'textarea'): ?>
            <label><?php echo e($field['label']); ?></label>
            <textarea name="<?php echo e($setKey); ?>" style="max-width:100%;width:100%;min-height:80px"><?php echo e($cur); ?></textarea>
            <?php elseif ($field['type'] === 'select'): ?>
            <label><?php echo e($field['label']); ?></label>
            <select name="<?php echo e($setKey); ?>">
                <?php foreach ($field['options'] as $optValue => $optLabel): ?>
                <option value="<?php echo e($optValue); ?>" <?php echo $cur === $optValue ? 'selected' : ''; ?>><?php echo e($optLabel); ?></option>
                <?php endforeach; ?>
            </select>
            <?php else: ?>
            <label><?php echo e($field['label']); ?></label>
            <input type="text" name="<?php echo e($setKey); ?>" maxlength="<?php echo (int) $field['maxlength']; ?>" value="<?php echo e($cur); ?>">
            <?php endif; ?>
            <?php if ($field['hint'] !== ''): ?>
            <p class="tip" style="margin:4px 0 0"><?php echo e($field['hint']); ?></p>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <button class="btn" type="submit">保存</button>
    </form>
    <?php endif; ?>
</div>
