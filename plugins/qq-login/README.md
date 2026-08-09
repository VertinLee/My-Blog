# QQ登录（qq-login）

使用 QQ 账号登录本站：登录页注入 QQ 图标入口，后台个人资料页支持绑定 / 解绑 / 换绑。

## 接入步骤

1. 在 [QQ 开放平台](https://connect.qq.com/) 创建「网站应用」并完成审核；
2. 后台 → 插件 → QQ登录，填入 APP ID / APP Key；
3. 将设置页展示的**回调地址**（`https://你的域名/qqlogin-callback`）原样登记到开放平台；
4. 用户到「后台 → 个人资料」绑定 QQ，此后即可在登录页点击 QQ 图标直接登录。

## 设计要点

- **仅绑定登录**：QQ 登录不会自动注册新账号，必须先绑定已有账号（防垃圾账号）；
- **state 双重防伪**：HMAC-SHA256 签名 + 会话随机数一次性校验（10 分钟有效），防 CSRF 与重放；
- **回调限流**：同一 IP 每分钟最多 10 次回调；
- **绑定存储**：`plugin_data` 表用户级作用域（`qq_openid` / `qq_nickname`），一个 QQ 只能绑定一个账号；
- **TLS 强校验**：所有对 graph.qq.com 的请求强制验证证书；
- **密钥保护**：APP Key 与签名密钥不出现在页面回显、日志中；设置页留空表示保持不变。

## 依赖的内核能力

- 自定义路由：`route_parse` 过滤器 + `front_route_{name}` 动作（回调 / 解绑端点）
- 主题钩子：`auth_form_footer`（登录页入口）、后台视图钩子 `profile_cards`（绑定卡片）
- 数据 API：`plugin_option` / `plugin_data_*` / `plugin_user_*`

插件卸载时内核自动清理全部配置与绑定数据，无残留。
