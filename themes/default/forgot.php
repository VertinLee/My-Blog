<?php
/**
 * 默认主题：找回密码页
 */
defined('APP_BOOT') or exit;
Theme::part('header');
?>
<div id="content">
    <div class="auth-site-name"><a href="<?php echo e(Router::url('home')); ?>"><?php echo e(site_name()); ?></a></div>
    <div class="auth-card">
        <h1>找回密码</h1>
        <?php if ($info !== ''): ?>
        <div class="form-ok"><?php echo e($info); ?></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
        <div class="form-error"><?php echo e($error); ?></div>
        <?php endif; ?>
        <?php if ($emailEnabled || $smsEnabled): ?>
        <form class="auth-form" method="post" action="<?php echo e(Router::url('forgot')); ?>"
              data-pwd-check data-account="#forgotAccount">
            <?php echo Csrf::field(); ?>
            <p>
                <label>用户名或邮箱</label>
                <input type="text" name="account" id="forgotAccount"
                       value="<?php echo e($account); ?>" required>
            </p>
            <div class="form-hint">输入用户名时通过绑定手机发送短信；输入邮箱时通过邮箱发送验证码</div>
            <p>
                <label>验证码</label>
                <span class="verify-row">
                    <input type="text" name="code" maxlength="6" required>
                    <button type="button" class="btn-small" id="sendResetCode"
                        data-scene="reset" data-channel="<?php echo $smsEnabled ? 'sms' : 'email'; ?>" data-target="#forgotAccount">发送验证码</button>
                </span>
            </p>
            <p>
                <label>新密码（8-64 位；大写字母/小写字母/数字/特殊字符至少三类；不得含用户名）</label>
                <input type="password" name="new_password" required minlength="8" maxlength="64">
            </p>
            <p>
                <label>确认新密码</label>
                <input type="password" name="new_password2" required minlength="8" maxlength="64">
                <?php // 二次确认实时反馈：一致/不一致由 password_check.js 填充 ?>
                <span class="pwd-match" aria-live="polite"></span>
            </p>
            <p class="form-actions"><button class="submit" type="submit">重置密码</button></p>
        </form>
        <?php else: ?>
        <p class="form-hint">站点未启用邮箱或短信验证插件，无法在线找回密码，请联系管理员。</p>
        <?php endif; ?>
        <?php do_action('auth_form_footer', 'forgot'); /* 插件注入点（找回表单下方），与登录/注册页同钩子不同页面参数 */ ?>
        <div class="auth-links"><a href="<?php echo e(Router::url('login')); ?>">返回登录</a></div>
    </div>
    <script>
    window.CB_VERIFY = {
        url: <?php echo json_out_script(Router::url('verify_send')); ?>,
        csrf: <?php echo json_out_script(Csrf::token()); ?>
    };
    </script>
    <script src="<?php echo e(assets_url('front/verify.js')); ?>"></script>
    <script src="<?php echo e(assets_url('front/password_check.js')); ?>"></script>
<?php Theme::part('footer'); ?>
