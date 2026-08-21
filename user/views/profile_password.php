<?php
/**
 * 后台视图：修改密码（含密码过期强制改密场景；layui 表单）
 */
defined('APP_BOOT') or exit;
?>
<div class="card">
    <?php if ($expired): ?>
    <div class="admin-flash err" style="margin-bottom:14px">
        <i class="layui-icon layui-icon-tips"></i> <?php echo e(admin_t('admin.profile.pwd_expired_notice')); ?>
    </div>
    <?php endif; ?>
    <form method="post" action="<?php echo e(site_base_admin('profile/password_save')); ?>"
          data-pwd-check data-username="<?php echo e(Auth::user()['username']); ?>" class="layui-form v-form">
        <?php echo Csrf::field(); ?>
        <div class="layui-form-item">
            <label class="v-label"><?php echo e(admin_t('admin.profile.old_pwd')); ?></label>
            <input type="password" name="old_password" autocomplete="current-password" required class="layui-input">
        </div>
        <div class="layui-form-item">
            <label class="v-label"><?php echo e(admin_t('admin.profile.new_pwd')); ?></label>
            <input type="password" name="new_password" autocomplete="new-password" required minlength="8" maxlength="64" class="layui-input">
        </div>
        <div class="layui-form-item">
            <label class="v-label"><?php echo e(admin_t('admin.profile.confirm_pwd')); ?></label>
            <input type="password" name="confirm_password" autocomplete="new-password" required minlength="8" maxlength="64" class="layui-input">
            <?php // 二次确认实时反馈：一致/不一致由 password_check.js 填充 ?>
            <span class="pwd-match" aria-live="polite"></span>
        </div>
        <button class="layui-btn" type="submit"><i class="layui-icon layui-icon-ok"></i> <?php echo e(admin_t('admin.profile.confirm_btn')); ?></button>
    </form>
</div>
<script>
// 后台语言包对 password_check.js 校验提示的覆盖注入
window.CB_PWD_LANG = <?php echo json_out_script(array(
    'need'          => admin_t('admin.pwd.need'),
    'len'           => admin_t('admin.pwd.len'),
    'classes'       => admin_t('admin.pwd.classes'),
    'contain_name'  => admin_t('admin.pwd.contain_name'),
    'repeat'        => admin_t('admin.pwd.repeat'),
    'mismatch'      => admin_t('admin.pwd.mismatch'),
    'mismatch_live' => admin_t('admin.pwd.mismatch_live'),
    'match_ok'      => admin_t('admin.pwd.match_ok'),
)); ?>;
</script>
<script src="<?php echo e(assets_url('front/password_check.js')); ?>"></script>
