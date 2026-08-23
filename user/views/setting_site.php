<?php
/**
 * 后台视图：站点设置（layui 表单；布尔项经 form 模块渲染为开关）
 * 变量由 AdminSetting::siteAction() 注入：$langList（可用语言列表）
 */
defined('APP_BOOT') or exit;
// 常用时区候选（保存时服务端会校验是否为 PHP 合法时区标识）；地区名走语言包
$timezones = array(
    'Asia/Shanghai'      => 'admin.setting.tz.asia_shanghai',
    'Asia/Hong_Kong'     => 'admin.setting.tz.asia_hong_kong',
    'Asia/Taipei'        => 'admin.setting.tz.asia_taipei',
    'Asia/Singapore'     => 'admin.setting.tz.asia_singapore',
    'Asia/Tokyo'         => 'admin.setting.tz.asia_tokyo',
    'Asia/Seoul'         => 'admin.setting.tz.asia_seoul',
    'Asia/Bangkok'       => 'admin.setting.tz.asia_bangkok',
    'Asia/Kolkata'       => 'admin.setting.tz.asia_kolkata',
    'Asia/Dubai'         => 'admin.setting.tz.asia_dubai',
    'Europe/London'      => 'admin.setting.tz.europe_london',
    'Europe/Paris'       => 'admin.setting.tz.europe_paris',
    'Europe/Athens'      => 'admin.setting.tz.europe_athens',
    'Europe/Moscow'      => 'admin.setting.tz.europe_moscow',
    'America/New_York'   => 'admin.setting.tz.america_new_york',
    'America/Chicago'    => 'admin.setting.tz.america_chicago',
    'America/Denver'     => 'admin.setting.tz.america_denver',
    'America/Los_Angeles' => 'admin.setting.tz.america_los_angeles',
    'America/Sao_Paulo'  => 'admin.setting.tz.america_sao_paulo',
    'Australia/Sydney'   => 'admin.setting.tz.australia_sydney',
    'Pacific/Auckland'   => 'admin.setting.tz.pacific_auckland',
    'UTC'                => 'admin.setting.tz.utc',
);
$currentTimezone = Option::get('timezone', 'Asia/Shanghai');
$currentLocale = Lang::code();
$switchText = e(admin_t('admin.common.on')) . '|' . e(admin_t('admin.common.off'));
?>
<div class="card">
    <form method="post" action="<?php echo e(site_base_admin('setting/site_save')); ?>" class="layui-form v-form">
        <?php echo Csrf::field(); ?>
        <div class="layui-form-item">
            <label class="v-label"><?php echo e(admin_t('admin.setting.site_name')); ?></label>
            <input type="text" name="site_name" value="<?php echo e(Option::get('site_name', '个人博客')); ?>" required class="layui-input">
        </div>
        <div class="layui-form-item">
            <label class="v-label"><?php echo e(admin_t('admin.setting.site_motto')); ?></label>
            <input type="text" name="site_motto" value="<?php echo e(Option::get('site_motto', '')); ?>" class="layui-input">
        </div>
        <div class="layui-form-item">
            <label class="v-label"><?php echo e(admin_t('admin.setting.site_desc')); ?></label>
            <textarea name="site_description" rows="2" class="layui-textarea"><?php echo e(Option::get('site_description', '')); ?></textarea>
        </div>
        <div class="layui-form-item">
            <label class="v-label"><?php echo e(admin_t('admin.setting.site_keywords')); ?></label>
            <input type="text" name="site_keywords" value="<?php echo e(Option::get('site_keywords', '')); ?>" class="layui-input">
        </div>
        <div class="layui-form-item">
            <label class="v-label"><?php echo e(admin_t('admin.setting.per_page')); ?></label>
            <input type="number" name="posts_per_page" min="1" max="50"
                   value="<?php echo (int) Option::get('posts_per_page', 10); ?>" class="layui-input">
        </div>
        <div class="layui-form-item">
            <label class="v-label"><?php echo e(admin_t('admin.setting.admin_locale')); ?></label>
            <select name="admin_locale">
                <?php foreach ($langList as $code => $name): ?>
                <option value="<?php echo e($code); ?>" <?php echo $currentLocale === $code ? 'selected' : ''; ?>>
                    <?php echo e($name); ?> [<?php echo e($code); ?>]
                </option>
                <?php endforeach; ?>
            </select>
            <div class="form-hint"><?php echo e(admin_t('admin.setting.admin_locale_hint')); ?></div>
        </div>
        <div class="layui-form-item">
            <label class="v-label"><?php echo e(admin_t('admin.setting.timezone')); ?></label>
            <select name="timezone">
                <?php foreach ($timezones as $tzId => $tzKey): ?>
                <option value="<?php echo e($tzId); ?>" <?php echo $currentTimezone === $tzId ? 'selected' : ''; ?>>
                    <?php echo e(admin_t($tzKey)); ?> [<?php echo e($tzId); ?>]
                </option>
                <?php endforeach; ?>
                <?php if (!isset($timezones[$currentTimezone])): ?>
                <option value="<?php echo e($currentTimezone); ?>" selected><?php echo e($currentTimezone); ?></option>
                <?php endif; ?>
            </select>
        </div>
        <?php // 布尔开关：lay-skin=switch 由 layui form 渲染；无 JS 时回退为原生勾选框 ?>
        <div class="layui-form-item">
            <label class="v-label"><?php echo e(admin_t('admin.setting.register_disabled')); ?></label>
            <input type="checkbox" name="register_disabled" lay-skin="switch" lay-text="<?php echo $switchText; ?>" value="1"
                   <?php echo Option::get('register_disabled', '0') === '1' ? 'checked' : ''; ?>>
            <div class="form-hint"><?php echo e(admin_t('admin.setting.register_disabled_hint')); ?></div>
        </div>
        <div class="layui-form-item">
            <label class="v-label"><?php echo e(admin_t('admin.setting.post_audit')); ?></label>
            <input type="checkbox" name="post_audit" lay-skin="switch" lay-text="<?php echo $switchText; ?>" value="1"
                   <?php echo Option::get('post_audit', '0') === '1' ? 'checked' : ''; ?>>
            <div class="form-hint"><?php echo e(admin_t('admin.setting.post_audit_hint')); ?></div>
        </div>
        <div class="layui-form-item">
            <label class="v-label"><?php echo e(admin_t('admin.setting.comment_audit')); ?></label>
            <input type="checkbox" name="comment_audit" lay-skin="switch" lay-text="<?php echo $switchText; ?>" value="1"
                   <?php echo Option::get('comment_audit', '0') === '1' ? 'checked' : ''; ?>>
            <div class="form-hint"><?php echo e(admin_t('admin.setting.comment_audit_hint')); ?></div>
        </div>
        <div class="layui-form-item">
            <label class="v-label"><?php echo e(admin_t('admin.setting.rewrite')); ?></label>
            <input type="checkbox" name="rewrite_enabled" lay-skin="switch" lay-text="<?php echo $switchText; ?>" value="1"
                   <?php echo Option::get('rewrite_enabled', '0') === '1' ? 'checked' : ''; ?>>
            <div class="form-hint"><?php echo e(admin_t('admin.setting.rewrite_hint')); ?></div>
        </div>
        <button class="layui-btn" type="submit"><i class="layui-icon layui-icon-ok"></i> <?php echo e(admin_t('admin.common.save')); ?></button>
    </form>
</div>
