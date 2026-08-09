<?php
/**
 * 后台单一入口：/user/login、/user/admin、/user/center 均由此分发
 */

require dirname(__DIR__) . '/core/bootstrap.php';
require APP_ROOT . '/core/Admin.php';
require APP_ROOT . '/core/AdminPost.php';
require APP_ROOT . '/core/AdminComment.php';
require APP_ROOT . '/core/AdminCategory.php';
require APP_ROOT . '/core/AdminUser.php';
require APP_ROOT . '/core/AdminSetting.php';
require APP_ROOT . '/core/AdminTheme.php';
require APP_ROOT . '/core/AdminPlugin.php';
require APP_ROOT . '/core/AdminLog.php';
require APP_ROOT . '/core/AdminProfile.php';
require APP_ROOT . '/core/AdminUpload.php';
// 个人资料改绑邮箱/手机复用前台的验证码核验逻辑，需加载 Front 类
require APP_ROOT . '/core/Front.php';

Admin::handle();
