# 插件开发规范

本博客系统的插件机制仿照 WordPress 规范实现：目录即插件、头部元数据声明、Hook 接入、零框架依赖。

## 1. 目录结构

每个插件是 `plugins/{slug}/` 目录，主文件必须为 `{slug}.php`：

```
plugins/
└── hello-world/
    └── hello-world.php    ← 主文件（必需）
```

- `slug` 只允许小写字母、数字、连字符（`[a-z0-9-]{1,64}`）。
- 主文件缺失或头部元数据缺少 `Plugin Name` 的目录不会被识别为插件。
- 插件静态资源（JS/CSS/图片）放在插件目录内，用 `plugin_url($slug, $path)` 生成 URL；
  禁止引用任何 CDN 资源。

## 2. 头部元数据

```php
<?php
/**
 * Plugin Name: Hello World
 * Description: 插件开发示例
 * Version: 1.0.0
 * Author: Your Name
 * Requires: 1.0
 */
defined('APP_BOOT') or exit;
```

第二行的防护语句必须保留：防止文件被浏览器直接访问执行。

## 3. Hook 机制

语义与 WordPress 一致（`core/Hook.php`）：

- `add_action($hook, $callback, $priority = 10)` / `do_action($hook, ...$args)`
- `add_filter($hook, $callback, $priority = 10)` / `apply_filters($hook, $value, ...$args)`

插件主文件在内核 `init` 之后被加载，**请勿再挂 `init` 钩子**（此时已触发完毕），
直接挂业务钩子即可。

### 预置钩子一览

| 钩子 | 类型 | 参数 | 说明 |
|---|---|---|---|
| `front_head` | action | 无 | 前台 `</head>` 前输出点 |
| `front_footer` | action | 无 | 前台页尾输出点 |
| `admin_head` | action | 无 | 后台 `</head>` 前输出点（插件后台样式/脚本） |
| `admin_footer` | action | 无 | 后台 `</body>` 前输出点 |
| `post_content` | filter | `$html` | 文章正文渲染后、输出前（可追加处理） |
| `front_posts` | filter | `$posts, $context` | 首页/归档/搜索列表当前页文章数组，可排序/修饰（`$context` 为 home/category/author/search；分页已在此之前确定，不宜移除条目） |
| `comment_before_save` | filter | `$data` | 评论入库前，可改写或拦截 |
| `admin_menu` | action | 无 | 注册后台菜单项/设置页 |
| `user_register` | action | 注册数据 | 注册前置校验点位 |
| `password_reset` | action | 找回数据 | 找回密码前置校验点位 |
| `send_verify_code` | filter | `$handled, $scene, $target, $channel, $code` | 返回 `true` 表示已接管发送 |
| `verify_code_check` | filter | `$result, $scene, $target, $code, $channel` | 返回 `bool` 接管核验；返回 `null` 走本地表 |
| `password_blacklist` | filter | `$list` | 扩展弱口令黑名单 |
| `route_parse` | filter | `$route, $path` | 未知路径落入 404 前认领自定义路由 |
| `front_route_{路由名}` | action | `$params` | 接管经 `route_parse` 认领的路由 |
| `auth_form_footer` | action | `$page` | 登录表单下方注入点（主题触发，默认 `$page='login'`） |
| `post_card_before` / `post_card_after` | action | `$post, $context` | 列表页文章卡片前/后注入点（主题触发，适合加徽标/推荐位等） |
| `profile_cards` | action | `$user` | 后台个人资料页追加卡片（内核视图触发） |
| `plugin_activate` / `plugin_deactivate` / `plugin_uninstall` | action | `$slug` | 插件生命周期 |

## 3.1 自定义路由（OAuth 回调 / Webhook 等）

内核路由表为硬编码，插件通过两步接管未知路径（参考 `plugins/qq-login`）：

```php
// 第一步：认领路径，返回自有路由名
add_filter('route_parse', 'my_claim_route', 10);
function my_claim_route($route, $path)
{
    if ($path === 'my-callback') {
        return array('route' => 'my_callback', 'params' => array());
    }
    return $route;
}

// 第二步：注册处理动作（未注册则照常 404）
add_action('front_route_my_callback', 'my_handle_callback');
function my_handle_callback($params)
{
    // GET/POST 自行处理；改状态的 POST 必须 Csrf::verifyOrDie()
}
```

URL 生成需兼容伪静态开关：开启时 `Router::base() . '/my-callback'`，
关闭时 `Router::base() . '/index.php?r=' . rawurlencode('my-callback')`。

## 4. 插件 API

| 函数 | 说明 |
|---|---|
| `plugin_option($slug, $key, $default)` | 读取插件配置（options 键 `plugin_{slug}_{key}`） |
| `plugin_option_update($slug, $key, $value)` | 写入插件配置 |
| `plugin_data_set($slug, $key, $value, $ttl)` | 全局数据写入（ttl>0 即临时缓存，到期自动失效） |
| `plugin_data_get($slug, $key, $default)` | 全局数据读取（过期视为不存在） |
| `plugin_data_delete($slug, $key)` | 全局数据删除 |
| `plugin_user_set($slug, $userId, $key, $value)` | 用户级数据写入（第三方账号绑定等） |
| `plugin_user_get($slug, $userId, $key, $default)` | 用户级数据读取 |
| `plugin_user_delete($slug, $userId, $key)` | 用户级数据删除 |
| `plugin_register_table($slug, $name)` | 登记自建表（卸载时内核自动 DROP） |
| `plugin_table($slug, $name)` | 自建表完整表名（含前缀） |
| `plugin_log($action, $detail)` | 写统一审计日志（category 固定 plugin） |
| `plugin_url($slug, $path)` | 插件静态资源 URL |
| `register_plugin_page($slug, $title, $callback)` | 注册后台设置页（在 `admin_menu` 钩子中调用） |

其他可用内核 API：`add_action/add_filter`、`e()`、`Router::url()`、`Option::get()`、
`DB::query()`（结构化查询构造器，值自动参数绑定）、`blog_log()`、`client_ip()` 等。
**禁止**在插件中直接拼接 SQL、直接读 `$_GET`/`$_POST`
原值、输出未转义变量——请复用内核的统一输入校验器与 `e()`。

### 4.1 插件数据存储（plugin_data 表）

一张表覆盖三种场景，插件永远不写 SQL、不管建表：

- **全局配置**：`plugin_data_set/get`（永久）；简单键值也可继续用 `plugin_option`；
- **用户级键值**：`plugin_user_set/get/delete`（替代 usermeta，如 QQ openid 绑定，
  反查可直接 `DB::query('plugin_data')` 按 `data_value` 条件查询）；
- **临时缓存**：`plugin_data_set(..., $ttl)`，过期由内核每日惰性清理。

数组值自动 JSON 编码；读取返回字符串，需数组时自行 `json_decode`。

### 4.2 卸载自动清理

后台「插件管理 → 删除」时内核自动清理：`plugin_{slug}_*` 全部选项、
`plugin_data` 中该插件的行、经 `plugin_register_table()` 登记的自建表。
插件无需自写清理代码；但因此也**不得**把数据写到上述范围之外
（如直接改 users 表结构），否则卸载后残留。

## 5. 后台设置页

在 `admin_menu` 钩子中注册，回调内输出 HTML（自行用 `e()` 转义）：

```php
add_action('admin_menu', function () {
    register_plugin_page('hello-world', 'Hello World', 'hello_world_page');
});

function hello_world_page()
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // 后台所有 POST 均已由内核统一校验 CSRF，此处直接读取经校验器的输入
        $text = input_text('hw_text', '', 100, 'post');
        plugin_option_update('hello-world', 'text', $text);
        plugin_log('hello-world.save', array('result' => 'success'));
        echo '<p class="tip">已保存</p>';
    }
    $text = plugin_option('hello-world', 'text', '你好，世界');
    echo '<form method="post">';
    echo Csrf::field();
    echo '<div class="form-row"><label>文案</label>'
        . '<input type="text" name="hw_text" value="' . e($text) . '"></div>';
    echo '<button class="btn" type="submit">保存</button></form>';
}
```

设置页入口自动收进后台左侧「插件」二级菜单（仅 `manage_plugins` 权限用户可见）；
未启用任何插件（无设置页注册）时该菜单不显示。设置页表单必须携带
`Csrf::field()`（内核对后台 POST 统一校验，缺失会返回 419）。

## 6. 验证码插件契约（`send_verify_code` / `verify_code_check`）

- 场景（`$scene`）取值：`register`（注册）、`reset`（找回密码）、
  `profile`（后台个人资料改绑邮箱/手机，仅登录用户可触发）。
- 发送：内核已完成频率限制（60s 间隔、每日 ≤10）后调用过滤器，参数为
  `($handled=false, $scene, $target, $channel, $code)`。
  插件接管时须自行把验证码写入 `verify_codes` 表（或采用云端生成模式），
  然后返回 `true`；返回其它值视为未接管。
- 核验：`($result=null, $scene, $target, $code, $channel)`。返回 `bool` 即接管；
  返回 `null` 回退本地 `verify_codes` 表核验（含 5 次错误作废逻辑）。

## 7. 安全要求（违反将造成系统级漏洞）

1. 所有输出必须 `e()` 转义；富文本须白名单过滤。
2. 不得绕过 CSRF（内核已对后台 POST 统一校验，插件设置页无需重复实现，但不得自建旁路表单）。
3. 日志不得含明文密码、验证码、密钥、授权码；邮箱/手机号必须脱敏。
4. 不得引入 Composer 依赖与运行时 CDN 资源。
5. PHP 7.2 语法兼容（禁止箭头函数、`??=`、尾随逗号调用等）。

## 8. 完整示例

- `plugins/hello-world/hello-world.php`：钩子与设置页基础用法。
- `plugins/qq-login/`：自定义路由（OAuth 回调）、登录页/个人资料页钩子注入、
  `plugin_data` 用户级绑定存储、限流与 state 防伪的完整实战。
