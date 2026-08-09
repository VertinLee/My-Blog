<?php
/**
 * 后台视图：主题设置（ICP/公安备案号，前台页脚展示）
 */
defined('APP_BOOT') or exit;
$icp = isset($settings['icp_number']) ? $settings['icp_number'] : '';
$gongan = isset($settings['gongan_number']) ? $settings['gongan_number'] : '';
?>
<div class="card">
    <h3>主题设置：<?php echo e($name); ?>（<?php echo e($dir); ?>）</h3>
    <p class="tip">
        备案号展示于前台页脚版权行下方：任一项留空则该项不显示；两项均填写时以「|」分隔。
        公安备案号会自动提取其中的数字编号，用于拼接公安部备案查询链接。
    </p>
    <form method="post" action="<?php echo e(site_base_admin('theme/setting_save')); ?>">
        <?php echo Csrf::field(); ?>
        <input type="hidden" name="dir" value="<?php echo e($dir); ?>">
        <div class="form-row">
            <label>ICP 备案号（如：京ICP备12345678号-1，链接至工信部备案系统）</label>
            <input type="text" name="icp_number" maxlength="64" value="<?php echo e($icp); ?>">
        </div>
        <div class="form-row">
            <label>公安备案号（如：京公网安备11040102700068号，链接至公安部备案查询页）</label>
            <input type="text" name="gongan_number" maxlength="100" value="<?php echo e($gongan); ?>">
        </div>
        <button class="btn" type="submit">保存</button>
    </form>
</div>
