<?php
/**
 * 默认主题：登录页
 */
defined('APP_BOOT') or exit;
Theme::part('header');
?>
<div id="content">
    <div class="auth-card">
        <h1>登录</h1>
        <?php foreach (flash_pull() as $fm): ?>
        <div class="<?php echo $fm['type'] === 'success' ? 'form-ok' : 'form-error'; ?>"><?php echo e($fm['text']); ?></div>
        <?php endforeach; ?>
        <?php if ($error !== ''): ?>
        <div class="form-error"><?php echo e($error); ?></div>
        <?php endif; ?>
        <form class="auth-form" method="post" action="<?php echo e(Router::url('login')); ?>">
            <?php echo Csrf::field(); ?>
            <p>
                <label>用户名或邮箱</label>
                <input type="text" name="account" value="<?php echo e($account); ?>" required>
            </p>
            <p>
                <label>密码</label>
                <input type="password" name="password" required>
            </p>
            <p class="form-actions"><button class="submit" type="submit">登录</button></p>
        </form>
        <div class="auth-links">
            <a href="<?php echo e(Router::url('forgot')); ?>">忘记密码？</a>
            <?php if (Option::get('register_disabled', '0') !== '1'): ?>
            · <a href="<?php echo e(Router::url('register')); ?>">注册新账号</a>
            <?php endif; ?>
        </div>
    </div>
<?php Theme::part('footer'); ?>
