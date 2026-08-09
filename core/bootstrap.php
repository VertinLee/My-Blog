<?php
/**
 * 内核引导：加载 config、DB、Session、Hook、Logger、插件；统一安全头与错误处理
 */

define('APP_BOOT', true);
define('APP_ROOT', dirname(__DIR__));
define('APP_VERSION', '1.0.0');

require APP_ROOT . '/core/Config.php';
require APP_ROOT . '/core/Utils.php';
require APP_ROOT . '/core/DB.php';
require APP_ROOT . '/core/Hook.php';
require APP_ROOT . '/core/Option.php';
require APP_ROOT . '/core/Csrf.php';
require APP_ROOT . '/core/Router.php';
require APP_ROOT . '/core/Auth.php';
require APP_ROOT . '/core/Logger.php';
require APP_ROOT . '/core/Markdown.php';
require APP_ROOT . '/core/Plugin.php';
require APP_ROOT . '/core/Theme.php';

/**
 * 错误处理：debug=0 时禁止向页面输出路径/SQL/堆栈，仅写服务器错误日志
 */
function app_error_handler($errno, $errstr, $errfile, $errline)
{
    error_log(sprintf('[blog] %s at %s:%d', $errstr, $errfile, $errline));
    if (Option::get('debug', '0') === '1') {
        return false; // 调试模式交给默认输出
    }
    return true;
}

function app_exception_handler($ex)
{
    error_log('[blog] exception: ' . $ex->getMessage() . ' at ' . $ex->getFile() . ':' . $ex->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    if (Option::get('debug', '0') === '1') {
        echo '<pre>' . e($ex->getMessage()) . '</pre>';
    } else {
        echo '服务器内部错误，请稍后再试';
    }
    exit;
}

set_error_handler('app_error_handler');
set_exception_handler('app_exception_handler');

// 未安装（config.php 不存在）时跳转安装程序；安装程序自身不加载本引导
if (!Config::load(APP_ROOT . '/config.php')) {
    $installUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://'
        . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost');
    $dir = str_replace('\\', '/', dirname(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '/'));
    // 从 user/、install/ 子目录入口进入时回退到站点根，避免拼出 /user/install/ 错误路径
    $dir = preg_replace('#/(user|install)$#', '', $dir);
    $base = ($dir === '/' || $dir === '.') ? '' : rtrim($dir, '/');
    header('Location: ' . $installUrl . $base . '/install/');
    exit;
}

// 数据库连接
DB::init(array(
    'host'    => Config::get('db.host', '127.0.0.1'),
    'port'    => Config::get('db.port', 3306),
    'name'    => Config::get('db.name', ''),
    'user'    => Config::get('db.user', ''),
    'pass'    => Config::get('db.pass', ''),
    'prefix'  => Config::get('db.prefix', 'cb_'),
    'charset' => 'utf8mb4',
));

// 存量站结构惰性升级：verify_codes.scene 补 profile 场景（新装已含，幂等只跑一次）；
// 升级失败仅记录不阻断站点，待下次请求重试
if (Option::get('schema_scene_profile', '0') !== '1') {
    try {
        $table = DB::table('verify_codes');
        $col = DB::pdo()->query("SHOW COLUMNS FROM `{$table}` LIKE 'scene'")->fetch();
        if ($col && strpos($col['Type'], 'profile') === false) {
            DB::pdo()->exec("ALTER TABLE `{$table}` MODIFY COLUMN `scene` ENUM('register','reset','profile') NOT NULL");
        }
        Option::set('schema_scene_profile', '1');
    } catch (Exception $ex) {
        error_log('[blog] schema upgrade failed: ' . $ex->getMessage());
    }
}

// 存量站结构惰性升级：users 表补封禁/注销标记列（新装已含，幂等只跑一次）
if (Option::get('schema_user_flags', '0') !== '1') {
    try {
        $table = DB::table('users');
        $cols = DB::pdo()->query("SHOW COLUMNS FROM `{$table}`")->fetchAll();
        $names = array();
        foreach ($cols as $col) {
            $names[] = $col['Field'];
        }
        if (!in_array('is_banned', $names, true)) {
            DB::pdo()->exec("ALTER TABLE `{$table}` ADD COLUMN `is_banned` TINYINT NOT NULL DEFAULT 0");
        }
        if (!in_array('is_deleted', $names, true)) {
            DB::pdo()->exec("ALTER TABLE `{$table}` ADD COLUMN `is_deleted` TINYINT NOT NULL DEFAULT 0");
        }
        Option::set('schema_user_flags', '1');
    } catch (Exception $ex) {
        error_log('[blog] schema upgrade failed: ' . $ex->getMessage());
    }
}

// 站点时区：默认 UTC+8（Asia/Shanghai），影响 now()/date_fmt()/日志等全部时间
// 非法值回退默认，避免 date() 报警告
$timezone = Option::get('timezone', 'Asia/Shanghai');
if (!in_array($timezone, timezone_identifiers_list(), true)) {
    $timezone = 'Asia/Shanghai';
}
date_default_timezone_set($timezone);

// 错误显示策略：debug 关闭时绝不向页面暴露错误
if (Option::get('debug', '0') === '1') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
    ini_set('log_errors', '1');
}

// Session：HttpOnly + HTTPS 下 Secure；空闲超时在 Auth::checkSessionTimeout 处理
$secure = is_https();
// 注意：数组形式为 PHP 7.3+ 语法，此处使用 7.2 兼容的位置参数形式
session_set_cookie_params(0, '/', '', $secure, true);
// SameSite 在 PHP 7.3+ 经 ini 生效，7.2 下自动忽略
ini_set('session.cookie_samesite', 'Lax');
session_name('cb_sid');
session_start();

Auth::checkSessionTimeout();

// 安全响应头（内核统一输出）
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-XSS-Protection: 1; mode=block');

// 加载已启用插件并触发 init
Plugin::loadActive();
do_action('init');

// 审计日志留存期惰性清理（每日最多一次）
Logger::purgeExpired();
