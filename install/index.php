<?php
/**
 * 安装程序：向导式四步（环境自检→数据库→管理员→执行安装）
 * 安装完成生成 install.lock 后拒绝再次访问
 */

define('APP_BOOT', true);
define('APP_ROOT', dirname(__DIR__));

require __DIR__ . '/schema.php';
require APP_ROOT . '/core/Hook.php';
require APP_ROOT . '/core/Auth.php';
// 统一输入校验器与 is_https()（input_* 为纯函数，不依赖 DB/Option，可在安装上下文安全使用）
require APP_ROOT . '/core/Utils.php';

// 安装程序自身也使用 UTC+8，保证初始时间戳（管理员建号、site_created_year）与站点默认时区一致
date_default_timezone_set('Asia/Shanghai');

session_name('cb_install_sid');
// 与内核 bootstrap 同策略：HTTPS 判定复用 is_https()（含 HTTPS='off' 与 443 端口识别）
session_set_cookie_params(array(
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => is_https(),
    'httponly' => true,
    'samesite' => 'Lax',
));
session_start();

// 已安装则跳转首页（双重守卫：install.lock 或 config.php 任一存在即视为已安装；
// 防止锁文件被误删后经重装覆盖 config.php 导致站点被接管）
if (is_file(__DIR__ . '/install.lock') || is_file(APP_ROOT . '/config.php')) {
    $dir = str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'])));
    $base = ($dir === '/' || $dir === '.') ? '' : rtrim($dir, '/');
    header('Location: ' . $base . '/');
    exit;
}

/* ---------- 安装程序内部助手 ---------- */

/** 简易转义 */
function ins_e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** 安装向导 CSRF token */
function ins_csrf()
{
    if (empty($_SESSION['ins_csrf'])) {
        $_SESSION['ins_csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['ins_csrf'];
}

/** 校验 CSRF，失败终止 */
function ins_csrf_check()
{
    $token = input_text('_csrf', '', 128, 'post');
    if ($token === '' || !hash_equals(ins_csrf(), $token)) {
        exit('CSRF 校验失败，请返回重试');
    }
}

/** 当前步骤（1-4） */
function ins_step()
{
    return max(1, min(4, input_int('step', 1, 'get')));
}

/** 安装程序基地址 */
function ins_base()
{
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    return ($dir === '/' || $dir === '.') ? '' : rtrim($dir, '/');
}

/** 站点根 URL */
function ins_site_base()
{
    $dir = str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'])));
    return ($dir === '/' || $dir === '.') ? '' : rtrim($dir, '/');
}

/* ---------- 步骤一：环境自检 ---------- */

function ins_env_check()
{
    $items = array();
    $items[] = array(
        'name' => 'PHP 版本 ≥ 7.4',
        'ok'   => version_compare(PHP_VERSION, '7.4.0', '>='),
        'info' => '当前 ' . PHP_VERSION,
    );
    foreach (array('pdo_mysql', 'mbstring', 'openssl', 'json', 'curl', 'fileinfo', 'gd') as $ext) {
        $items[] = array(
            'name' => '扩展 ' . $ext,
            'ok'   => extension_loaded($ext),
            'info' => extension_loaded($ext) ? '已加载' : '未加载',
        );
    }
    $rootWritable = is_writable(APP_ROOT);
    $items[] = array(
        'name' => '站点根目录可写（生成 config.php）',
        'ok'   => $rootWritable,
        'info' => $rootWritable ? '可写' : '不可写',
    );
    // is_dir 前置判断已避开"已存在"警告；真失败（权限等）由下方 is_writable 检查项呈现
    if (!is_dir(APP_ROOT . '/uploads')) {
        mkdir(APP_ROOT . '/uploads', 0755, true);
    }
    if (!is_dir(APP_ROOT . '/uploads/avatars')) {
        mkdir(APP_ROOT . '/uploads/avatars', 0755, true);
    }
    $uploadsWritable = is_writable(APP_ROOT . '/uploads');
    $items[] = array(
        'name' => 'uploads/ 目录可写',
        'ok'   => $uploadsWritable,
        'info' => $uploadsWritable ? '可写' : '不可写',
    );
    // 重写能力探测（非阻断项）
    $sapi = PHP_SAPI;
    $rewriteInfo = '';
    $rewriteOk = true;
    if (stripos($sapi, 'apache') !== false && function_exists('apache_get_modules')) {
        $rewriteOk = in_array('mod_rewrite', apache_get_modules(), true);
        $rewriteInfo = $rewriteOk ? 'mod_rewrite 已加载' : 'mod_rewrite 未加载（可回退 index.php?r= 模式）';
    } else {
        $rewriteInfo = '非 Apache 环境（Nginx 请按 nginx.conf.example 配置；未配置时可回退 index.php?r= 模式）';
    }
    $items[] = array('name' => 'URL 重写能力', 'ok' => true, 'info' => $rewriteInfo, 'soft' => true);
    return $items;
}

/* ---------- 步骤二/四：数据库 ---------- */

/**
 * 建立 PDO 连接（安装程序专用）
 */
function ins_pdo($host, $port, $user, $pass, $dbname = '')
{
    // DSN 注入防护：host 仅允许主机名/IP/IPv6 字符集，dbname 拒绝分隔符，
    // 防止 ";host=evil" 之类输入把连接重定向到攻击者控制的 MySQL
    if (!preg_match('/^[A-Za-z0-9.\-:]+$/', (string) $host)) {
        throw new InvalidArgumentException('数据库主机名包含非法字符');
    }
    if ($dbname !== '' && strpos($dbname, ';') !== false) {
        throw new InvalidArgumentException('数据库名包含非法字符');
    }
    $dsn = 'mysql:host=' . $host . ';port=' . (int) $port . ';charset=utf8mb4';
    if ($dbname !== '') {
        $dsn .= ';dbname=' . $dbname;
    }
    return new PDO($dsn, $user, $pass, array(
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5,
    ));
}

/** 保存步骤二表单到 Session */
function ins_db_config_from_session()
{
    $d = isset($_SESSION['ins_db']) ? $_SESSION['ins_db'] : array();
    return array_merge(array(
        'host' => '127.0.0.1', 'port' => '3306', 'user' => '', 'pass' => '', 'name' => '', 'prefix' => 'cb_',
    ), $d);
}

/* ---------- 步骤四：执行安装 ---------- */

function ins_do_install($dbConf, $admin)
{
    $pdo = ins_pdo($dbConf['host'], $dbConf['port'], $dbConf['user'], $dbConf['pass'], $dbConf['name']);
    $prefix = $dbConf['prefix'];

    // 建表
    foreach (install_schema($prefix) as $sql) {
        $pdo->exec($sql);
    }

    $now = date('Y-m-d H:i:s');

    // 默认 options（安全默认值与 plan.md §4 一致，禁止调低安全水位）
    $options = array(
        'site_name'               => isset($admin['site_name']) && $admin['site_name'] !== '' ? $admin['site_name'] : '我的博客',
        'site_motto'              => isset($admin['site_motto']) ? $admin['site_motto'] : '',
        'site_description'        => '',
        'site_created_year'       => date('Y'),
        'posts_per_page'          => '10',
        'post_audit'              => '0',
        'comment_audit'           => '0',
        'active_theme'            => 'default',
        'active_plugins'          => '[]',
        'rewrite_enabled'         => isset($admin['rewrite_enabled']) ? $admin['rewrite_enabled'] : '1',
        'timezone'                => 'Asia/Shanghai',
        'register_disabled'       => '0',
        'log_retention_days'      => '180',
        'login_max_fail'          => '5',
        'login_lock_minutes'      => '10',
        'session_timeout_minutes' => '30',
        'pwd_expire_enabled'      => '0',
        'pwd_expire_days'         => '90',
        'pwd_history_count'       => '0',
        'ip_header_enabled'       => '0',
        'ip_header_name'          => 'X-Forwarded-For',
        'debug'                   => '0',
    );
    $stmt = $pdo->prepare('INSERT INTO `' . $prefix . 'options` (option_key, option_value) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)');
    foreach ($options as $key => $value) {
        $stmt->execute(array($key, $value));
    }

    // 管理员账号（role=admin，password_changed_at 初始化为当前时间）
    $check = $pdo->prepare('SELECT id FROM `' . $prefix . 'users` WHERE username = ?');
    $check->execute(array($admin['username']));
    if (!$check->fetch()) {
        $ins = $pdo->prepare('INSERT INTO `' . $prefix . 'users`
            (username, nickname, password, email, phone, role, status, password_changed_at, login_fail, created_at)
            VALUES (?, ?, ?, ?, ?, "admin", 1, ?, 0, ?)');
        $ins->execute(array(
            $admin['username'],
            $admin['nickname'] !== '' ? $admin['nickname'] : $admin['username'],
            password_hash($admin['password'], PASSWORD_DEFAULT),
            $admin['email'],
            $admin['phone'],
            $now,
            $now,
        ));
    }

    // 默认分类与欢迎文章
    $catCount = $pdo->query('SELECT COUNT(*) FROM `' . $prefix . 'categories`')->fetchColumn();
    if ((int) $catCount === 0) {
        $pdo->exec('INSERT INTO `' . $prefix . 'categories` (name, slug, description, sort) VALUES
            ("默认分类", "default", "站点默认分类", 0)');
    }
    $postCount = $pdo->query('SELECT COUNT(*) FROM `' . $prefix . 'posts`')->fetchColumn();
    if ((int) $postCount === 0) {
        $adminId = (int) $pdo->query('SELECT id FROM `' . $prefix . 'users` WHERE role = "admin" ORDER BY id ASC LIMIT 1')->fetchColumn();
        $welcome = "# 欢迎使用本博客系统\n\n这是安装程序创建的第一篇文章，你可以在后台编辑或删除它。\n\n- Markdown 编写\n- 本地化静态资源\n- 等保二级安全设计";
        $p = $pdo->prepare('INSERT INTO `' . $prefix . 'posts`
            (author_id, title, slug, content, excerpt, category_id, status, is_page, views, created_at, updated_at)
            VALUES (?, "你好，世界", "hello-world", ?, ?, 1, "published", 0, 0, ?, ?)');
        $p->execute(array($adminId, $welcome, mb_substr(strip_tags($welcome), 0, 120), $now, $now));
    }

    // 生成 config.php（DB 配置 + 64 位随机密钥；var_export 与 PHP 源码语义同构，天然安全转义）
    $key = bin2hex(random_bytes(32));
    $configData = array(
        'db' => array(
            'host'   => $dbConf['host'],
            'port'   => (int) $dbConf['port'],
            'name'   => $dbConf['name'],
            'user'   => $dbConf['user'],
            'pass'   => $dbConf['pass'],
            'prefix' => $prefix,
        ),
        'key' => $key,
    );
    $config = "<?php\n"
        . "/**\n * 站点配置：由安装程序生成，请勿泄露；禁止 HTTP 直接访问\n */\n"
        . "defined('APP_BOOT') or exit;\n\n"
        . 'return ' . var_export($configData, true) . ";\n";
    if (file_put_contents(APP_ROOT . '/config.php', $config) === false) {
        throw new RuntimeException('写入 config.php 失败，请检查站点根目录权限');
    }

    // 安装锁
    file_put_contents(__DIR__ . '/install.lock', 'installed at ' . $now);
}

/* ---------- 请求处理 ---------- */

$step = ins_step();
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ins_csrf_check();
    $action = input_enum('action', array('test_db', 'save_db', 'do_install'), '', 'post');

    // 测试数据库连接
    if ($action === 'test_db') {
        // 密码留空时沿用 Session 暂存值（页面不再回显明文密码）
        $prevDb = ins_db_config_from_session();
        $dbHost = input_text('host', '', 255, 'post');
        $dbPort = input_int('port', 3306, 'post');
        $dbUser = input_text('user', '', 64, 'post');
        $dbName = input_text('name', '', 64, 'post');
        $dbPrefix = input_text('prefix', 'cb_', 32, 'post');
        if (!preg_match('/^[A-Za-z0-9_]+$/', $dbPrefix)) {
            $dbPrefix = 'cb_';
        }
        $passInput = input_password('pass');
        $passUse = $passInput !== '' ? $passInput : $prevDb['pass'];
        try {
            $pdo = ins_pdo($dbHost, $dbPort, $dbUser, $passUse, $dbName);
            $pdo->query('SELECT 1');
            $message = '数据库连接成功';
            $messageType = 'ok';
            $_SESSION['ins_db'] = array(
                'host' => $dbHost, 'port' => (string) $dbPort,
                'user' => $dbUser, 'pass' => $passUse,
                'name' => $dbName,
                'prefix' => $dbPrefix,
            );
        } catch (Exception $ex) {
            $message = '连接失败：' . $ex->getMessage();
            $messageType = 'err';
        }
        $step = 2;
    }

    // 提交数据库配置
    if ($action === 'save_db') {
        $dbPrefix = input_text('prefix', 'cb_', 32, 'post');
        if (!preg_match('/^[A-Za-z0-9_]+$/', $dbPrefix)) {
            $dbPrefix = 'cb_';
        }
        $prevDb = ins_db_config_from_session();
        $dbHost = input_text('host', '', 255, 'post');
        $dbPort = input_int('port', 3306, 'post');
        $dbUser = input_text('user', '', 64, 'post');
        $dbName = input_text('name', '', 64, 'post');
        $passInput = input_password('pass');
        $passUse = $passInput !== '' ? $passInput : $prevDb['pass'];
        try {
            $pdo = ins_pdo($dbHost, $dbPort, $dbUser, $passUse, $dbName);
            $pdo->query('SELECT 1');
            $_SESSION['ins_db'] = array(
                'host' => $dbHost, 'port' => (string) $dbPort,
                'user' => $dbUser, 'pass' => $passUse,
                'name' => $dbName, 'prefix' => $dbPrefix,
            );
            header('Location: ' . ins_base() . '/index.php?step=3');
            exit;
        } catch (Exception $ex) {
            $message = '数据库连接失败：' . $ex->getMessage();
            $messageType = 'err';
            $step = 2;
        }
    }

    // 提交管理员信息并执行安装
    if ($action === 'do_install') {
        if (empty($_SESSION['ins_db'])) {
            $message = '请先完成数据库配置';
            $messageType = 'err';
            $step = 2;
        } else {
            $username = input_text('username', '', 32, 'post');
            $nickname = input_text('nickname', '', 64, 'post');
            $password = input_password('password');
            $password2 = input_password('password2');
            // 邮箱/手机号用 input_text 保留原值：格式错误需给出明确提示，
            // 若用 input_email/input_phone 会把非法输入静默归空、跳过下方报错
            $email = input_text('email', '', 128, 'post');
            $phone = input_text('phone', '', 16, 'post');
            $siteName = input_text('site_name', '', 64, 'post');
            $motto = input_text('site_motto', '', 128, 'post');

            do {
                if (!preg_match('/^[a-zA-Z0-9_]{3,32}$/', $username)) {
                    $message = '用户名为 3-32 位字母、数字或下划线';
                    break;
                }
                if ($password !== $password2) {
                    $message = '两次输入的密码不一致';
                    break;
                }
                // 服务端强制口令复杂度校验（与注册/改密同规则）
                $pwdErr = Auth::validate_password_strength($password, $username);
                if ($pwdErr !== '') {
                    $message = $pwdErr;
                    break;
                }
                if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $message = '邮箱格式不正确';
                    break;
                }
                if ($phone !== '' && !preg_match('/^1[3-9]\d{9}$/', $phone)) {
                    $message = '手机号格式不正确';
                    break;
                }

                try {
                    ins_do_install($_SESSION['ins_db'], array(
                        'username' => $username, 'nickname' => $nickname, 'password' => $password,
                        'email' => $email, 'phone' => $phone,
                        'site_name' => $siteName, 'site_motto' => $motto,
                        'rewrite_enabled' => isset($_POST['rewrite_enabled']) ? '1' : '0',
                    ));
                    unset($_SESSION['ins_db']);
                    $step = 4;
                } catch (Exception $ex) {
                    $message = '安装失败：' . $ex->getMessage();
                    $messageType = 'err';
                    $step = 3;
                }
            } while (false);
            if ($message === '') {
                $step = 4;
            }
        }
    }
}

$envItems = $step === 1 ? ins_env_check() : array();
$envPass = true;
foreach ($envItems as $item) {
    if (!$item['ok'] && empty($item['soft'])) {
        $envPass = false;
    }
}
$dbConf = ins_db_config_from_session();
$steps = array(1 => '环境自检', 2 => '数据库配置', 3 => '管理员信息', 4 => '安装完成');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>安装向导 - 第 <?php echo $step; ?> 步</title>
<style>
/* 安装向导视觉对齐前台默认主题（宣纸极简）：米白纸面 + 青绿主色 + 细边框卡片 */
body{
    font-family:"Noto Serif SC","Songti SC","STSong","SimSun",serif;
    background:#F7F4ED;color:#2B2B2B;margin:0;padding:40px 16px;line-height:1.6;
}
.box{
    max-width:680px;margin:0 auto;background:#FBFAF6;
    border:1px solid #E0DBD0;border-radius:6px;
    box-shadow:0 1px 3px rgba(0,0,0,.08);padding:32px;
}
h1{font-size:22px;margin:0 0 20px;font-family:"Noto Serif SC","Songti SC","STSong","SimSun",serif}
.steps{display:flex;gap:8px;margin-bottom:24px;font-size:13px;flex-wrap:wrap}
.steps span{padding:4px 12px;border-radius:14px;background:#F0EDE5;border:1px solid #E0DBD0;color:#6B6B6B}
.steps span.on{background:#4A7C7E;border-color:#4A7C7E;color:#fff}
label{display:block;margin:12px 0 4px;font-size:14px;color:#555}
input[type=text],input[type=password],input[type=number],input[type=email],input[type=tel]{
    width:100%;box-sizing:border-box;padding:8px 12px;
    border:1px solid #E0DBD0;border-radius:6px;font-size:14px;
    background:#fff;color:#2B2B2B;outline:none;transition:border-color .2s;
}
input:focus{border-color:#4A7C7E}
.btn{
    display:inline-block;margin-top:20px;padding:9px 22px;
    background:#4A7C7E;color:#fff;border:0;border-radius:6px;
    font-size:14px;cursor:pointer;text-decoration:none;transition:background .2s;
}
.btn:hover{background:#3A5F5E}
.btn.gray{background:#8A93A3}
.btn.gray:hover{background:#767F8E}
.msg{padding:10px 14px;border-radius:6px;margin:14px 0;font-size:14px}
.msg.ok{background:rgba(74,124,126,.10);border:1px solid #4A7C7E;color:#3A5F5E}
.msg.err{background:rgba(180,80,60,.08);border:1px solid #B4503C;color:#B4503C}
/* 环境自检表：窄屏横向滚动不溢出 */
.table-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch}
table{width:100%;border-collapse:collapse;font-size:14px}
td,th{border-bottom:1px solid #E0DBD0;padding:8px 6px;text-align:left}
th{background:#F0EDE5;color:#6B6B6B;white-space:nowrap}
.pass{color:#2F9E5F}.fail{color:#B4503C}
.hint{font-size:12px;color:#999;margin-top:4px}
@media (max-width:480px){body{padding:20px 10px}.box{padding:20px 16px}}
</style>
</head>
<body>
<div class="box">
<h1>博客系统安装向导</h1>
<div class="steps">
<?php foreach ($steps as $i => $label): ?>
    <span class="<?php echo $i === $step ? 'on' : ''; ?>"><?php echo $i; ?>. <?php echo ins_e($label); ?></span>
<?php endforeach; ?>
</div>

<?php if ($message !== ''): ?>
<div class="msg <?php echo $messageType === 'ok' ? 'ok' : 'err'; ?>"><?php echo ins_e($message); ?></div>
<?php endif; ?>

<?php if ($step === 1): ?>
<div class="table-wrap">
<table>
<tr><th>检查项</th><th>结果</th><th>说明</th></tr>
<?php foreach ($envItems as $item): ?>
<tr>
    <td><?php echo ins_e($item['name']); ?></td>
    <td class="<?php echo $item['ok'] ? 'pass' : 'fail'; ?>"><?php echo $item['ok'] ? '✔ 通过' : '✘ 不通过'; ?></td>
    <td><?php echo ins_e($item['info']); ?></td>
</tr>
<?php endforeach; ?>
</table>
</div>
<?php if ($envPass): ?>
    <a class="btn" href="<?php echo ins_e(ins_base()); ?>/index.php?step=2">下一步：数据库配置</a>
<?php else: ?>
    <p class="fail">环境自检未通过，请先解决上述红色项后刷新本页。</p>
<?php endif; ?>

<?php elseif ($step === 2): ?>
<form method="post" action="<?php echo ins_e(ins_base()); ?>/index.php?step=2">
    <input type="hidden" name="_csrf" value="<?php echo ins_e(ins_csrf()); ?>">
    <label>数据库地址</label>
    <input type="text" name="host" value="<?php echo ins_e($dbConf['host']); ?>" required>
    <label>端口</label>
    <input type="number" name="port" value="<?php echo ins_e($dbConf['port']); ?>" required>
    <label>数据库名</label>
    <input type="text" name="name" value="<?php echo ins_e($dbConf['name']); ?>" required>
    <label>用户名</label>
    <input type="text" name="user" value="<?php echo ins_e($dbConf['user']); ?>" required>
    <div class="hint">遵循最小权限原则：仅需 SELECT/INSERT/UPDATE/DELETE/CREATE/INDEX/ALTER，禁止使用 root</div>
    <label>密码</label>
    <?php // 密码不回显到页面源码，避免明文泄露；Session 中仍保留供提交使用 ?>
    <input type="password" name="pass" value="" placeholder="<?php echo $dbConf['pass'] !== '' ? '已暂存，留空则沿用' : ''; ?>">
    <label>表前缀（仅字母数字下划线）</label>
    <input type="text" name="prefix" value="<?php echo ins_e($dbConf['prefix']); ?>" required>
    <button class="btn gray" type="submit" name="action" value="test_db">测试连接</button>
    <button class="btn" type="submit" name="action" value="save_db">下一步：管理员信息</button>
</form>

<?php elseif ($step === 3): ?>
<form method="post" action="<?php echo ins_e(ins_base()); ?>/index.php?step=3">
    <input type="hidden" name="_csrf" value="<?php echo ins_e(ins_csrf()); ?>">
    <label>站点名称</label>
    <input type="text" name="site_name" value="我的博客">
    <label>一句话座右铭（可选）</label>
    <input type="text" name="site_motto" value="">
    <label>管理员用户名</label>
    <input type="text" name="username" required>
    <label>昵称（可选）</label>
    <input type="text" name="nickname">
    <label>管理员密码</label>
    <input type="password" name="password" required>
    <div class="hint">8-64 位；大写字母/小写字母/数字/特殊字符至少三类；不含用户名；不得为常见弱口令</div>
    <label>确认密码</label>
    <input type="password" name="password2" required>
    <label>邮箱</label>
    <input type="text" name="email">
    <label>手机号（可选）</label>
    <input type="text" name="phone">
    <label><input type="checkbox" name="rewrite_enabled" checked> 已配置 URL 伪静态重写（未配置请取消勾选，将使用 index.php?r= 回退模式）</label>
    <button class="btn" type="submit" name="action" value="do_install">开始安装</button>
</form>

<?php else: ?>
<div class="msg ok">安装完成！请牢记管理员账号。</div>
<p>安全提示：install/ 目录已被 install.lock 锁定；建议删除或妥善保管安装程序。</p>
<a class="btn" href="<?php echo ins_e(ins_site_base()); ?>/">访问前台首页</a>
<a class="btn gray" href="<?php echo ins_e(ins_site_base()); ?>/user/index.php">进入后台登录</a>
<?php endif; ?>

</div>
</body>
</html>
