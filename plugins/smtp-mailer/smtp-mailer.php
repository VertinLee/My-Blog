<?php
/**
 * Plugin Name: SMTP Mailer
 * Description: 通过 SMTP 发送邮箱验证码与测试邮件（纯 PHP 实现，零依赖）
 * Version: 1.0.0
 * Author: Blog Team
 * Requires: 1.0
 */
defined('APP_BOOT') or exit;

require_once dirname(__FILE__) . '/SmtpClient.php';

/** 声明邮箱渠道验证码能力（内核入口探测/策略读取仅认声明，不硬编码插件名） */
register_verify_provider('email');

/** 接管邮箱验证码发送（channel=email） */
add_filter('send_verify_code', 'smtp_mailer_send', 10, 5);

/** 注册后台设置页 */
add_action('admin_menu', 'smtp_mailer_menu');

function smtp_mailer_menu()
{
    register_plugin_page('smtp-mailer', 'SMTP 发信设置', 'smtp_mailer_page');
}

/**
 * 读取插件 SMTP 配置
 *
 * @return array
 */
function smtp_mailer_config()
{
    return array(
        'host'       => (string) plugin_option('smtp-mailer', 'host', ''),
        'port'       => (int) plugin_option('smtp-mailer', 'port', 465),
        'encryption' => (string) plugin_option('smtp-mailer', 'encryption', 'ssl'),
        'username'   => (string) plugin_option('smtp-mailer', 'username', ''),
        'password'   => (string) plugin_option('smtp-mailer', 'password', ''),
        'from_name'  => (string) plugin_option('smtp-mailer', 'from_name', site_name()),
    );
}

/**
 * 构造客户端；配置不完整时返回 null
 *
 * @return SmtpClient|null
 */
function smtp_mailer_client()
{
    $cfg = smtp_mailer_config();
    if ($cfg['host'] === '' || $cfg['username'] === '') {
        return null;
    }
    return new SmtpClient(
        $cfg['host'], $cfg['port'], $cfg['encryption'],
        $cfg['username'], $cfg['password'], $cfg['from_name']
    );
}

/**
 * send_verify_code 过滤器回调：写入 verify_codes 并发送验证码邮件
 *
 * @param mixed  $handled 上游是否已接管
 * @param string $scene   register/reset
 * @param string $target  邮箱
 * @param string $channel email/sms
 * @param string $code    内核生成的 6 位验证码
 * @return bool
 */
function smtp_mailer_send($handled, $scene, $target, $channel, $code)
{
    if ($handled === true || $channel !== 'email') {
        return $handled;
    }
    $client = smtp_mailer_client();
    if ($client === null) {
        plugin_log('smtp.send', array('result' => 'fail', 'reason' => 'not_configured'));
        return false;
    }

    $subject = '【' . site_name() . '】邮箱验证码';
    $body = '您的验证码是：' . $code . "\n"
        . '验证码 10 分钟内有效，请勿泄露给他人。\n';
    $ok = $client->send($target, $subject, $body);
    if (!$ok) {
        // 错误信息可能含服务器响应，截断后入库；绝不包含授权码与验证码
        plugin_log('smtp.send', array(
            'result' => 'fail', 'scene' => $scene,
            'error' => mb_substr($client->error(), 0, 120),
        ));
        return false;
    }

    DB::insert('verify_codes', array(
        'scene'      => $scene,
        'target'     => $target,
        'code'       => $code,
        'channel'    => 'email',
        'expires_at' => date('Y-m-d H:i:s', time() + 600),
        'used'       => 0,
        'attempts'   => 0,
        'created_at' => now(),
    ));
    plugin_log('smtp.send', array('result' => 'success', 'scene' => $scene));
    return true;
}

/** 后台设置页：配置保存 + 测试邮件 */
function smtp_mailer_page()
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $op = input_enum('op', array('save', 'test'), 'save', 'post');
        if ($op === 'save') {
            plugin_option_update('smtp-mailer', 'host', input_text('host', '', 128, 'post'));
            plugin_option_update('smtp-mailer', 'port', input_enum('port', array('25', '465', '587'), '465', 'post'));
            plugin_option_update('smtp-mailer', 'encryption', input_enum('encryption', array('none', 'ssl', 'tls'), 'ssl', 'post'));
            plugin_option_update('smtp-mailer', 'username', input_email('username', '', 'post'));
            // 授权码留空表示不修改（避免回显明文）
            $password = input_password('password');
            if ($password !== '') {
                plugin_option_update('smtp-mailer', 'password', $password);
            }
            plugin_option_update('smtp-mailer', 'from_name', input_text('from_name', '', 64, 'post'));
            // 邮箱验证码错误容忍次数：内核核验时读取本配置（范围 1-5 由内核钳制），错满即作废
            plugin_option_update('smtp-mailer', 'max_attempts', max(1, min(5, input_int('max_attempts', 2, 'post'))));
            plugin_log('smtp.config', array('result' => 'success'));
            echo '<p class="tip">配置已保存。</p>';
        } else {
            $to = input_email('test_to', '', 'post');
            if ($to === '') {
                echo '<p class="tip">请填写有效的测试收件邮箱。</p>';
            } else {
                $client = smtp_mailer_client();
                if ($client === null) {
                    echo '<p class="tip">请先完成 SMTP 配置。</p>';
                } else {
                    $ok = $client->send($to, '【' . site_name() . '】测试邮件', '这是一封测试邮件，SMTP 配置正常。');
                    plugin_log('smtp.test', array('result' => $ok ? 'success' : 'fail'));
                    echo $ok
                        ? '<p class="tip">测试邮件已发送，请查收。</p>'
                        : '<p class="tip">发送失败：' . e(mb_substr($client->error(), 0, 200)) . '</p>';
                }
            }
        }
    }

    $cfg = smtp_mailer_config();
    echo '<form method="post">';
    echo Csrf::field();
    echo '<input type="hidden" name="op" value="save">';
    echo '<div class="form-row"><label>SMTP 主机</label>'
        . '<input type="text" name="host" value="' . e($cfg['host']) . '" placeholder="smtp.example.com"></div>';
    echo '<div class="form-row"><label>端口</label><select name="port">';
    foreach (array('25', '465', '587') as $portOption) {
        $selected = (string) $cfg['port'] === $portOption ? ' selected' : '';
        echo '<option value="' . $portOption . '"' . $selected . '>' . $portOption . '</option>';
    }
    echo '</select></div>';
    echo '<div class="form-row"><label>加密方式</label><select name="encryption">';
    $encNames = array('none' => '无', 'ssl' => 'SSL', 'tls' => 'STARTTLS');
    foreach ($encNames as $encKey => $encName) {
        $selected = $cfg['encryption'] === $encKey ? ' selected' : '';
        echo '<option value="' . $encKey . '"' . $selected . '>' . $encName . '</option>';
    }
    echo '</select></div>';
    echo '<div class="form-row"><label>账号（发件邮箱）</label>'
        . '<input type="email" name="username" value="' . e($cfg['username']) . '"></div>';
    echo '<div class="form-row"><label>授权码（留空表示不修改）</label>'
        . '<input type="password" name="password" autocomplete="new-password"></div>';
    echo '<div class="form-row"><label>发件人名称</label>'
        . '<input type="text" name="from_name" value="' . e($cfg['from_name']) . '"></div>';
    echo '<div class="form-row"><label>验证码错误容忍次数（次，1-5，默认 2；错满即作废，需重新发送）</label>'
        . '<input type="number" name="max_attempts" min="1" max="5" value="'
        . max(1, min(5, (int) plugin_option('smtp-mailer', 'max_attempts', 2))) . '"></div>';
    echo '<button class="btn" type="submit">保存配置</button>';
    echo '</form>';

    echo '<hr style="margin:20px 0;border:none;border-top:1px solid #e5e5e5">';
    echo '<form method="post" class="form-inline">';
    echo Csrf::field();
    echo '<input type="hidden" name="op" value="test">';
    echo '<input type="email" name="test_to" placeholder="测试收件邮箱" required>';
    echo '<button class="btn gray" type="submit">发送测试邮件</button>';
    echo '</form>';
}
