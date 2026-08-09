<?php
/**
 * Plugin Name: Aliyun SMS
 * Description: 阿里云号码认证服务短信验证码（手写 HMAC-SHA1 RPC 签名，仅依赖 cURL）
 * Version: 1.0.0
 * Author: Blog Team
 * Requires: 1.0
 */
defined('APP_BOOT') or exit;

require_once dirname(__FILE__) . '/AliyunRpc.php';

add_filter('send_verify_code', 'aliyun_sms_send', 10, 5);
add_filter('verify_code_check', 'aliyun_sms_check', 10, 5);
add_action('admin_menu', 'aliyun_sms_menu');

function aliyun_sms_menu()
{
    register_plugin_page('aliyun-sms', '阿里云短信设置', 'aliyun_sms_page');
}

/** 读取插件配置；密钥类字段统一去首尾空白，避免复制粘贴带入的空白破坏签名 */
function aliyun_sms_config()
{
    return array(
        'access_key_id'     => trim((string) plugin_option('aliyun-sms', 'access_key_id', '')),
        'access_key_secret' => trim((string) plugin_option('aliyun-sms', 'access_key_secret', '')),
        'sign_name'         => trim((string) plugin_option('aliyun-sms', 'sign_name', '')),
        // 默认使用系统赠送模板（搭配赠送签名）
        'template_code'     => trim((string) plugin_option('aliyun-sms', 'template_code', '100001')),
        'scheme_name'       => trim((string) plugin_option('aliyun-sms', 'scheme_name', 'Default')),
        'code_length'       => max(4, min(8, (int) plugin_option('aliyun-sms', 'code_length', 6))),
        'valid_time'        => max(1, (int) plugin_option('aliyun-sms', 'valid_time', 10)),
        'interval'          => max(0, (int) plugin_option('aliyun-sms', 'interval', 60)),
    );
}

/** 构造 RPC 客户端；配置不完整返回 null */
function aliyun_sms_client($cfg = null)
{
    if ($cfg === null) {
        $cfg = aliyun_sms_config();
    }
    if ($cfg['access_key_id'] === '' || $cfg['access_key_secret'] === '') {
        return null;
    }
    // 仅支持云端验证码模式，固定走号码认证服务
    return new AliyunRpc($cfg['access_key_id'], $cfg['access_key_secret'], 'https://dypnsapi.aliyuncs.com');
}

/** 阿里云错误码 → 友好提示 */
function aliyun_sms_error_message($code)
{
    $map = array(
        'MOBILE_NUMBER_ILLEGAL' => '手机号格式不正确',
        'BUSINESS_LIMIT_CONTROL' => '发送过于频繁，请稍后再试',
        'FREQUENCY_FAIL' => '触发频率限制，请稍后再试',
        'INVALID_PARAMETERS' => '参数不正确，请检查插件配置',
        'FUNCTION_NOT_OPENED' => '阿里云号码认证服务未开通',
        // 网关 HMAC 校验失败：几乎都是 AccessKeySecret 与 AccessKeyId 不匹配或含多余空白
        'SignatureDoesNotMatch' => '接口签名校验失败：请核对 AccessKeySecret 与 AccessKeyId 是否匹配，重新粘贴时注意勿带空格/换行',
    );
    if (isset($map[$code])) {
        return $map[$code];
    }
    return '短信发送失败（' . $code . '）';
}

/**
 * 发送验证码（channel=sms）：SendSmsVerifyCode，##code## 由阿里云生成与核验
 */
function aliyun_sms_send($handled, $scene, $target, $channel, $code)
{
    if ($handled === true || $channel !== 'sms') {
        return $handled;
    }
    $cfg = aliyun_sms_config();
    $client = aliyun_sms_client($cfg);
    if ($client === null || $cfg['template_code'] === '') {
        plugin_log('aliyun-sms.send', array('result' => 'fail', 'reason' => 'not_configured'));
        return false;
    }

    $resp = $client->call('SendSmsVerifyCode', array(
        'PhoneNumber'   => $target,
        'CountryCode'   => '86',
        'CodeLength'    => $cfg['code_length'],
        'CodeType'      => '1',
        // 官方文档：ValidTime 单位为秒（默认 300），本地配置为分钟，需换算
        'ValidTime'     => $cfg['valid_time'] * 60,
        'Interval'      => $cfg['interval'],
        // 官方文档：仅支持系统赠送签名（需搭配赠送模板），不支持自定义签名
        'SignName'      => $cfg['sign_name'],
        'TemplateCode'  => $cfg['template_code'],
        'TemplateParam' => json_encode(array('code' => '##code##', 'min' => (string) $cfg['valid_time'])),
        'SchemeName'    => $cfg['scheme_name'],
        'OutId'         => $scene,
    ));

    if ($resp === null) {
        plugin_log('aliyun-sms.send', array('result' => 'fail', 'reason' => 'network', 'scene' => $scene));
        return false;
    }
    $respCode = isset($resp['Code']) ? (string) $resp['Code'] : '';
    if ($respCode !== 'OK') {
        plugin_log('aliyun-sms.send', array('result' => 'fail', 'scene' => $scene, 'api_code' => $respCode));
        return false;
    }

    // 核验走阿里云，但本地频控（60s/每日上限）依赖 verify_codes 记录，
    // 故仍写入一条记账（used=1 表示不参与本地核验）
    DB::insert('verify_codes', array(
        'scene'      => $scene,
        'target'     => $target,
        'code'       => $code,
        'channel'    => 'sms',
        'expires_at' => date('Y-m-d H:i:s', time() + $cfg['valid_time'] * 60),
        'used'       => 1,
        'attempts'   => 0,
        'created_at' => now(),
    ));
    plugin_log('aliyun-sms.send', array('result' => 'success', 'scene' => $scene));
    return true;
}

/**
 * 核验验证码：调用 CheckSmsVerifyCode 接管 sms 通道核验
 */
function aliyun_sms_check($result, $scene, $target, $code, $channel)
{
    if ($result !== null || $channel !== 'sms') {
        return $result;
    }
    $cfg = aliyun_sms_config();
    $client = aliyun_sms_client($cfg);
    if ($client === null) {
        return false;
    }
    $resp = $client->call('CheckSmsVerifyCode', array(
        'PhoneNumber' => $target,
        'VerifyCode'  => $code,
        'SchemeName'  => $cfg['scheme_name'],
    ));
    if ($resp === null) {
        plugin_log('aliyun-sms.check', array('result' => 'fail', 'reason' => 'network', 'scene' => $scene));
        return false;
    }
    $respCode = isset($resp['Code']) ? (string) $resp['Code'] : '';
    if ($respCode !== 'OK') {
        plugin_log('aliyun-sms.check', array('result' => 'fail', 'scene' => $scene, 'api_code' => $respCode));
        return false;
    }
    $ok = isset($resp['Model']['VerifyResult']) && $resp['Model']['VerifyResult'];
    plugin_log('aliyun-sms.check', array('result' => $ok ? 'success' : 'fail', 'scene' => $scene));
    return $ok;
}

/** 后台设置页 */
function aliyun_sms_page()
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        plugin_option_update('aliyun-sms', 'access_key_id', input_text('access_key_id', '', 64, 'post'));
        // AccessKeySecret 留空表示不修改（避免回显明文）；
        // 剔除全部空白：阿里云密钥不含空白字符，复制粘贴带入的空格/换行会直接导致 SignatureDoesNotMatch
        $secret = isset($_POST['access_key_secret']) ? (string) $_POST['access_key_secret'] : '';
        $secret = preg_replace('/\s+/', '', $secret);
        if ($secret !== '') {
            plugin_option_update('aliyun-sms', 'access_key_secret', $secret);
        }
        plugin_option_update('aliyun-sms', 'sign_name', input_text('sign_name', '', 32, 'post'));
        plugin_option_update('aliyun-sms', 'template_code', input_text('template_code', '', 64, 'post'));
        // 官方文档：SchemeName 最多 20 个字符，留空则使用默认方案
        plugin_option_update('aliyun-sms', 'scheme_name', input_text('scheme_name', '', 20, 'post'));
        plugin_option_update('aliyun-sms', 'code_length', max(4, min(8, input_int('code_length', 6, 'post'))));
        plugin_option_update('aliyun-sms', 'valid_time', max(1, min(60, input_int('valid_time', 10, 'post'))));
        plugin_option_update('aliyun-sms', 'interval', max(0, min(3600, input_int('interval', 60, 'post'))));
        plugin_log('aliyun-sms.config', array('result' => 'success'));
        echo '<p class="tip">配置已保存。</p>';
    }

    $cfg = aliyun_sms_config();
    echo '<form method="post">';
    echo Csrf::field();
    echo '<div class="form-row"><label>AccessKeyId</label>'
        . '<input type="text" name="access_key_id" value="' . e($cfg['access_key_id']) . '"></div>';
    echo '<div class="form-row"><label>AccessKeySecret（留空表示不修改）</label>'
        . '<input type="password" name="access_key_secret" autocomplete="new-password"></div>';
    echo '<div class="form-row"><label>短信签名 SignName（仅支持号码认证控制台“赠送签名”，需搭配赠送模板）</label>'
        . '<input type="text" name="sign_name" value="' . e($cfg['sign_name']) . '"></div>';
    echo '<div class="form-row"><label>模板 TemplateCode（赠送模板，默认 100001）</label>'
        . '<input type="text" name="template_code" value="' . e($cfg['template_code']) . '"></div>';
    echo '<div class="form-row"><label>方案名 SchemeName（≤20 字符，默认 Default）</label>'
        . '<input type="text" name="scheme_name" value="' . e($cfg['scheme_name']) . '"></div>';
    echo '<div class="form-row"><label>验证码长度（4-8）</label>'
        . '<input type="number" name="code_length" min="4" max="8" value="' . (int) $cfg['code_length'] . '"></div>';
    echo '<div class="form-row"><label>有效期（分钟）</label>'
        . '<input type="number" name="valid_time" min="1" max="60" value="' . (int) $cfg['valid_time'] . '"></div>';
    echo '<div class="form-row"><label>重发间隔（秒，云端侧）</label>'
        . '<input type="number" name="interval" min="0" max="3600" value="' . (int) $cfg['interval'] . '"></div>';
    echo '<button class="btn" type="submit">保存配置</button>';
    echo '</form>';
    echo '<p class="tip">AccessKeySecret 保存后不会再次显示，日志中不会出现明文密钥与验证码。</p>';
}
