<?php
/**
 * 后台视图：修改密码（含密码过期强制改密场景）
 */
defined('APP_BOOT') or exit;
?>
<div class="card">
    <?php if ($expired): ?>
    <p class="tip" style="color:#c0392b">您的密码已过期，必须修改密码后才能继续使用后台。</p>
    <?php endif; ?>
    <form method="post" action="<?php echo e(site_base_admin('profile/password_save')); ?>"
          data-pwd-check data-username="<?php echo e(Auth::user()['username']); ?>">
        <?php echo Csrf::field(); ?>
        <div class="form-row">
            <label>当前密码</label>
            <input type="password" name="old_password" autocomplete="current-password" required>
        </div>
        <div class="form-row">
            <label>新密码（8-64 位；大写字母/小写字母/数字/特殊字符至少三类；不得含用户名）</label>
            <input type="password" name="new_password" autocomplete="new-password" required minlength="8" maxlength="64">
        </div>
        <div class="form-row">
            <label>确认新密码</label>
            <input type="password" name="confirm_password" autocomplete="new-password" required minlength="8" maxlength="64">
            <?php // 二次确认实时反馈：一致/不一致由 password_check.js 填充 ?>
            <span class="pwd-match" aria-live="polite"></span>
        </div>
        <button class="btn" type="submit">确认修改</button>
    </form>
</div>
<script src="<?php echo e(assets_url('front/password_check.js')); ?>"></script>
