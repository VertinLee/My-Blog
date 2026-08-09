<?php
/**
 * 默认主题：注册页（验证码按已启用插件动态出现）
 */
defined('APP_BOOT') or exit;
Theme::part('header');
?>
<div id="content">
    <div class="auth-site-name"><a href="<?php echo e(Router::url('home')); ?>"><?php echo e(site_name()); ?></a></div>
    <div class="auth-card">
        <h1>注册</h1>
        <?php if ($error !== ''): ?>
        <div class="form-error"><?php echo e($error); ?></div>
        <?php endif; ?>
        <form class="auth-form" method="post" action="<?php echo e(Router::url('register')); ?>" novalidate>
            <?php echo Csrf::field(); ?>
            <p>
                <label>用户名（3-32 位字母/数字/下划线）</label>
                <input type="text" name="username" id="regUsername" value="<?php echo e($old['username']); ?>" required>
            </p>
            <p>
                <label>昵称（可选）</label>
                <input type="text" name="nickname" value="<?php echo e($old['nickname']); ?>">
            </p>
            <p>
                <label>邮箱</label>
                <input type="email" name="email" id="regEmail" value="<?php echo e($old['email']); ?>">
            </p>
            <?php if ($emailEnabled): ?>
            <p>
                <label>邮箱验证码</label>
                <span class="verify-row">
                    <input type="text" name="email_code" id="regEmailCode" maxlength="6">
                    <button type="button" class="btn-small" id="sendEmailCode"
                        data-scene="register" data-channel="email" data-target="#regEmail">发送验证码</button>
                </span>
            </p>
            <?php endif; ?>
            <p>
                <?php // 短信插件启用时手机号必填且需验证码，不再标注可选 ?>
                <label>手机号<?php echo $smsEnabled ? '' : '（可选）'; ?></label>
                <input type="text" name="phone" id="regPhone" value="<?php echo e($old['phone']); ?>" <?php echo $smsEnabled ? 'required' : ''; ?>>
            </p>
            <?php if ($smsEnabled): ?>
            <p>
                <label>短信验证码</label>
                <span class="verify-row">
                    <input type="text" name="sms_code" id="regSmsCode" maxlength="6">
                    <button type="button" class="btn-small" id="sendSmsCode"
                        data-scene="register" data-channel="sms" data-target="#regPhone">发送验证码</button>
                </span>
            </p>
            <?php endif; ?>
            <p>
                <label>密码</label>
                <input type="password" name="password" id="regPassword" required>
            </p>
            <div class="pwd-rules" aria-live="polite">
                <span class="pwd-rule" id="rule-len">长度 8-64 位</span>
                <span class="pwd-rule" id="rule-upper">包含大写字母</span>
                <span class="pwd-rule" id="rule-lower">包含小写字母</span>
                <span class="pwd-rule" id="rule-digit">包含数字</span>
                <span class="pwd-rule" id="rule-special">包含特殊字符</span>
                <span class="pwd-rule" id="rule-classes">上述四类至少满足三类</span>
                <span class="pwd-rule" id="rule-username">不包含用户名</span>
            </div>
            <p>
                <label>确认密码</label>
                <input type="password" name="password2" id="regPassword2" required>
                <?php // 二次确认实时反馈：一致/不一致由 register_check.js 填充 ?>
                <span class="pwd-match" id="pwdMatch" aria-live="polite"></span>
            </p>
            <p class="form-actions"><button class="submit" type="submit">注册</button></p>
        </form>
        <div class="auth-links">已有账号？<a href="<?php echo e(Router::url('login')); ?>">直接登录</a></div>
    </div>
    <script>
    window.CB_VERIFY = {
        url: <?php echo json_encode(Router::url('verify_send')); ?>,
        csrf: <?php echo json_encode(Csrf::token()); ?>
    };
    </script>
    <script src="<?php echo e(assets_url('front/verify.js')); ?>"></script>
    <script src="<?php echo e(assets_url('front/pwd_check.js')); ?>"></script>
    <script src="<?php echo e(assets_url('front/register_check.js')); ?>"></script>
<?php Theme::part('footer'); ?>
