<?php
/**
 * 通用工具：HTML 转义、客户端 IP、统一输入校验器、脱敏、跳转等
 */
defined('APP_BOOT') or exit;

/**
 * HTML 输出转义：所有输出到页面的变量必须经此函数
 *
 * @param mixed $value 待转义值
 * @return string
 */
function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * 是否 HTTPS 环境
 *
 * @return bool
 */
function is_https()
{
    if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    return isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443;
}

/**
 * 客户端 IP 统一获取入口（全站唯一允许方式，禁止直接读 REMOTE_ADDR）
 * 默认只信 REMOTE_ADDR；仅当 ip_header_enabled=1 且指定标头名时才从标头取值，
 * 且必须经 FILTER_VALIDATE_IP 校验、XFF 逗号链取第一个 IP、非法回退 REMOTE_ADDR。
 * 标头名支持英文逗号分隔多个，在前优先级高，按序依次尝试，某个标头取到合法 IP 即停止
 *
 * @return string
 */
function client_ip()
{
    $remote = isset($_SERVER['REMOTE_ADDR']) ? trim($_SERVER['REMOTE_ADDR']) : '';
    if (!filter_var($remote, FILTER_VALIDATE_IP)) {
        $remote = '0.0.0.0';
    }
    if (Option::get('ip_header_enabled', '0') !== '1') {
        return $remote;
    }
    $raw = (string) Option::get('ip_header_name', 'X-Forwarded-For');
    // 多标头：英文逗号分隔，在前优先；逐个尝试，取到合法 IP 即返回
    foreach (explode(',', $raw) as $name) {
        $name = trim($name);
        // 标头名只允许字母、数字、连字符；空/非法片段跳过
        if ($name === '' || !preg_match('/^[A-Za-z0-9-]+$/', $name)) {
            continue;
        }
        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if (!isset($_SERVER[$serverKey]) || trim($_SERVER[$serverKey]) === '') {
            continue;
        }
        $list = explode(',', $_SERVER[$serverKey]);
        $first = trim($list[0]);
        if (filter_var($first, FILTER_VALIDATE_IP)) {
            return $first;
        }
    }
    return $remote;
}

/**
 * 客户端 User-Agent（截断 255）
 *
 * @return string
 */
function client_ua()
{
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    return mb_substr($ua, 0, 255);
}

/**
 * 302 跳转并终止
 *
 * @param string $url 目标地址
 * @return void
 */
function redirect($url)
{
    header('Location: ' . $url);
    exit;
}

/**
 * 输出 JSON 响应并终止
 *
 * @param array $data 响应数据（建议含 code/msg）
 * @return void
 */
function json_out(array $data)
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 统一输入校验器：整数
 *
 * @param string $key     参数名
 * @param int    $default 缺省值
 * @param string $source  get/post/request
 * @return int
 */
function input_int($key, $default = 0, $source = 'request')
{
    $src = input_source($source);
    if (!isset($src[$key]) || $src[$key] === '') {
        return $default;
    }
    $valid = filter_var($src[$key], FILTER_VALIDATE_INT);
    return $valid !== false ? (int) $valid : $default;
}

/**
 * 统一输入校验器：文本（长度上限，去首尾空白）
 *
 * @param string $key     参数名
 * @param string $default 缺省值
 * @param int    $maxLen  最大长度
 * @param string $source  get/post/request
 * @return string
 */
function input_text($key, $default = '', $maxLen = 255, $source = 'request')
{
    $src = input_source($source);
    if (!isset($src[$key]) || !is_string($src[$key])) {
        return $default;
    }
    $value = trim($src[$key]);
    if ($value === '') {
        return $default;
    }
    return mb_substr($value, 0, $maxLen);
}

/**
 * 统一输入校验器：长文本（正文等）
 *
 * @param string $key     参数名
 * @param string $default 缺省值
 * @param int    $maxLen  最大长度
 * @return string
 */
function input_longtext($key, $default = '', $maxLen = 200000)
{
    $src = input_source('post');
    if (!isset($src[$key]) || !is_string($src[$key])) {
        return $default;
    }
    $value = $src[$key];
    return mb_substr($value, 0, $maxLen);
}

/**
 * 统一输入校验器：邮箱
 *
 * @param string $key     参数名
 * @param string $default 缺省值
 * @param string $source  get/post/request
 * @return string 合法邮箱或空串
 */
function input_email($key, $default = '', $source = 'request')
{
    $src = input_source($source);
    $value = isset($src[$key]) ? trim((string) $src[$key]) : '';
    if ($value === '') {
        return $default;
    }
    return filter_var($value, FILTER_VALIDATE_EMAIL) ? mb_substr($value, 0, 128) : '';
}

/**
 * 统一输入校验器：中国大陆手机号
 *
 * @param string $key     参数名
 * @param string $default 缺省值
 * @param string $source  get/post/request
 * @return string 合法手机号或空串
 */
function input_phone($key, $default = '', $source = 'request')
{
    $src = input_source($source);
    $value = isset($src[$key]) ? trim((string) $src[$key]) : '';
    return preg_match('/^1[3-9]\d{9}$/', $value) ? $value : $default;
}

/**
 * 统一输入校验器：别名 slug（小写字母数字连字符）
 *
 * @param string $key     参数名
 * @param string $default 缺省值
 * @param string $source  get/post/request
 * @return string
 */
function input_slug($key, $default = '', $source = 'request')
{
    $src = input_source($source);
    $value = isset($src[$key]) ? trim((string) $src[$key]) : '';
    if (!preg_match('/^[a-z0-9][a-z0-9-]{0,99}$/', $value)) {
        return $default;
    }
    return $value;
}

/**
 * 统一输入校验器：枚举白名单
 *
 * @param string $key     参数名
 * @param array  $allowed 允许值列表
 * @param string $default 缺省值
 * @param string $source  get/post/request
 * @return string
 */
function input_enum($key, array $allowed, $default = '', $source = 'request')
{
    $src = input_source($source);
    $value = isset($src[$key]) ? (string) $src[$key] : '';
    return in_array($value, $allowed, true) ? $value : $default;
}

/**
 * 取输入源数组
 */
function input_source($source)
{
    if ($source === 'get') {
        return $_GET;
    }
    if ($source === 'post') {
        return $_POST;
    }
    return array_merge($_GET, $_POST);
}

/**
 * 邮箱脱敏：a***@x.com
 *
 * @param string $email 邮箱
 * @return string
 */
function mask_email($email)
{
    $at = strpos($email, '@');
    if ($at === false) {
        return '***';
    }
    $name = substr($email, 0, $at);
    $keep = substr($name, 0, 1);
    return $keep . '***@' . substr($email, $at + 1);
}

/**
 * 手机号脱敏：138****1234
 *
 * @param string $phone 手机号
 * @return string
 */
function mask_phone($phone)
{
    if (!preg_match('/^\d{11}$/', $phone)) {
        return '***';
    }
    return substr($phone, 0, 3) . '****' . substr($phone, 7);
}

/**
 * 随机字符串（字母数字）
 *
 * @param int $len 长度
 * @return string
 */
function random_string($len = 32)
{
    $out = '';
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    $max = strlen($chars) - 1;
    for ($i = 0; $i < $len; $i++) {
        $out .= $chars[random_int(0, $max)];
    }
    return $out;
}

/**
 * 当前时间（Y-m-d H:i:s）
 *
 * @return string
 */
function now()
{
    return date('Y-m-d H:i:s');
}

/**
 * Markdown 原文转纯文本摘要
 *
 * @param string $markdown 原文
 * @param int    $len      截取长度
 * @return string
 */
function excerpt_of($markdown, $len = 150)
{
    $text = preg_replace('/```[\s\S]*?```/u', ' ', (string) $markdown);
    $text = preg_replace('/[`*#>\[\]!\|\-]{1,3}/u', '', $text);
    $text = preg_replace('/\s+/u', ' ', trim($text));
    return mb_substr($text, 0, $len);
}

/**
 * 站点根相对 URL（拼接基础路径）
 *
 * @param string $path 以 / 开头的站内路径
 * @return string
 */
function site_url_path($path)
{
    return Router::base() . $path;
}

/**
 * 静态资源 URL（附文件修改时间版本号，避免浏览器缓存旧资源）
 *
 * @param string $path assets 下相对路径，如 admin/style.css
 * @return string
 */
function assets_url($path)
{
    $url = Router::base() . '/assets/' . ltrim($path, '/');
    $file = APP_ROOT . '/assets/' . ltrim($path, '/');
    if (is_file($file)) {
        $url .= '?v=' . filemtime($file);
    }
    return $url;
}

/**
 * 闪存消息写入（一次有效）
 *
 * @param string $type success/error
 * @param string $text 消息内容
 * @return void
 */
function flash_set($type, $text)
{
    $_SESSION['_flash'][] = array('type' => $type, 'text' => $text);
}

/**
 * 取出并清空全部闪存消息
 *
 * @return array
 */
function flash_pull()
{
    if (empty($_SESSION['_flash'])) {
        return array();
    }
    $list = $_SESSION['_flash'];
    unset($_SESSION['_flash']);
    return $list;
}

/**
 * 时间友好格式化
 *
 * @param string $datetime Y-m-d H:i:s
 * @return string
 */
function date_fmt($datetime)
{
    $ts = strtotime($datetime);
    if ($ts === false) {
        return '';
    }
    return date('Y 年 n 月 j 日', $ts);
}
