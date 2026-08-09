<?php
/**
 * 后台设置：站点设置（manage_options）与安全设置（manage_security）
 * 安全项变更全程审计；日志留存下限 180 天；密码过期/历史功能默认关闭
 */
defined('APP_BOOT') or exit;

class AdminSetting
{
    /** 站点设置页 */
    public static function siteAction()
    {
        Auth::require_cap('manage_options');
        Admin::render('站点设置', 'setting_site', array());
    }

    /** 保存站点设置 */
    public static function siteSaveAction()
    {
        Auth::require_cap('manage_options');
        $changed = array();
        $items = array(
            'site_name'        => input_text('site_name', '个人博客', 64, 'post'),
            'site_motto'       => input_text('site_motto', '', 128, 'post'),
            'site_description' => input_text('site_description', '', 255, 'post'),
            'site_keywords'    => input_text('site_keywords', '', 255, 'post'),
        );
        foreach ($items as $key => $value) {
            if ((string) Option::get($key, '') !== $value) {
                $changed[] = $key;
            }
            Option::set($key, $value);
        }

        $perPage = input_int('posts_per_page', 10, 'post');
        $perPage = max(1, min(50, $perPage));
        if ((int) Option::get('posts_per_page', 10) !== $perPage) {
            $changed[] = 'posts_per_page';
        }
        Option::set('posts_per_page', (string) $perPage);

        // 开关项：checkbox 未勾选即视为关闭
        $switches = array('post_audit', 'comment_audit', 'rewrite_enabled', 'register_disabled');
        foreach ($switches as $key) {
            $value = input_int($key, 0, 'post') === 1 ? '1' : '0';
            if (Option::get($key, '0') !== $value) {
                $changed[] = $key;
            }
            Option::set($key, $value);
        }

        // 时区：仅接受 PHP 合法时区标识，默认 UTC+8（Asia/Shanghai）
        $timezone = input_text('timezone', 'Asia/Shanghai', 64, 'post');
        if (!in_array($timezone, timezone_identifiers_list(), true)) {
            $timezone = 'Asia/Shanghai';
        }
        if ((string) Option::get('timezone', 'Asia/Shanghai') !== $timezone) {
            $changed[] = 'timezone';
        }
        Option::set('timezone', $timezone);

        blog_log('setting', 'setting.site', 'success', array('changed' => $changed));
        flash_set('success', '站点设置已保存');
        redirect(site_base_admin('setting/site'));
    }

    /** 安全设置页（等保二级相关控制点） */
    public static function securityAction()
    {
        Auth::require_cap('manage_security');
        Admin::render('安全设置', 'setting_security', array());
    }

    /** 保存安全设置 */
    public static function securitySaveAction()
    {
        Auth::require_cap('manage_security');
        $changed = array();

        $intRules = array(
            // 键 => array(缺省, 下限, 上限)
            'login_max_fail'        => array(5, 1, 20),
            'login_lock_minutes'    => array(10, 1, 1440),
            'session_timeout_minutes' => array(30, 1, 1440),
            'pwd_expire_days'       => array(90, 30, 365),
            'pwd_history_count'     => array(0, 0, 24),
            // 日志留存下限 180 天（《网络安全法》第 21 条不少于六个月）
            'log_retention_days'    => array(180, 180, 3650),
        );
        foreach ($intRules as $key => $rule) {
            $value = max($rule[1], min($rule[2], input_int($key, $rule[0], 'post')));
            if ((int) Option::get($key, $rule[0]) !== $value) {
                $changed[] = $key;
            }
            Option::set($key, (string) $value);
        }

        $switches = array('pwd_expire_enabled', 'ip_header_enabled', 'debug');
        foreach ($switches as $key) {
            $value = input_int($key, 0, 'post') === 1 ? '1' : '0';
            if (Option::get($key, '0') !== $value) {
                $changed[] = $key;
            }
            Option::set($key, $value);
        }

        // 自定义 IP 标头名：支持英文逗号分隔多个（在前优先级高），每段仅允许字母数字连字符
        $headerRaw = input_text('ip_header_name', 'X-Forwarded-For', 255, 'post');
        $headerParts = array();
        foreach (explode(',', $headerRaw) as $part) {
            $part = trim($part);
            if ($part !== '' && preg_match('/^[A-Za-z0-9-]+$/', $part)) {
                $headerParts[] = $part;
            }
        }
        // 去重保序；全部非法时回退默认值，保证该选项始终可用
        $headerParts = array_values(array_unique($headerParts));
        $headerName = empty($headerParts) ? 'X-Forwarded-For' : implode(',', $headerParts);
        if ((string) Option::get('ip_header_name', 'X-Forwarded-For') !== $headerName) {
            $changed[] = 'ip_header_name';
        }
        Option::set('ip_header_name', $headerName);

        // 密码历史开启时按需建表（预留功能，默认关闭）
        if ((int) Option::get('pwd_history_count', 0) > 0) {
            self::ensurePasswordHistoryTable();
        }

        blog_log('security', 'setting.security', 'success', array('changed' => $changed));
        flash_set('success', '安全设置已保存');
        redirect(site_base_admin('setting/security'));
    }

    /** 导航管理页（前台侧边栏自定义导航项） */
    public static function navAction()
    {
        Auth::require_cap('manage_options');
        Admin::render('导航管理', 'setting_nav', array('items' => nav_items()));
    }

    /** 保存导航项：既有行可原地编辑/勾选删除，末行追加一条新项 */
    public static function navSaveAction()
    {
        Auth::require_cap('manage_options');
        $items = array();
        foreach (nav_items() as $i => $row) {
            if (input_int('del_' . $i, 0, 'post') === 1) {
                continue;
            }
            $item = self::sanitizeNavItem(
                input_text('title_' . $i, '', 32, 'post'),
                input_text('url_' . $i, '', 255, 'post')
            );
            if ($item !== null) {
                $items[] = $item;
            }
        }
        $newItem = self::sanitizeNavItem(
            input_text('new_title', '', 32, 'post'),
            input_text('new_url', '', 255, 'post')
        );
        if ($newItem !== null && count($items) < 30) {
            $items[] = $newItem;
        }
        Option::set('nav_items', json_encode($items, JSON_UNESCAPED_UNICODE));
        blog_log('setting', 'setting.nav', 'success', array('count' => count($items)));
        flash_set('success', '导航项已保存');
        redirect(site_base_admin('setting/nav'));
    }

    /**
     * 单条导航项校验：标题/链接非空且 URL 命中白名单；非法返回 null
     * URL 只准站内相对路径（/ 开头、无协议头）或 http(s) 绝对地址，
     * 拒绝 javascript:/data: 等可执行协议与协议相对地址
     */
    private static function sanitizeNavItem($title, $url)
    {
        $title = trim((string) $title);
        $url = trim((string) $url);
        if ($title === '' || $url === '') {
            return null;
        }
        $isRelative = strpos($url, '/') === 0 && strpos($url, '//') !== 0;
        $isAbsolute = (bool) preg_match('#^https?://#i', $url);
        $isPlain = strpos($url, ':') === false;
        if (!$isRelative && !$isAbsolute && !$isPlain) {
            return null;
        }
        return array('title' => $title, 'url' => $url);
    }

    /** 按需创建 password_history 表 */
    private static function ensurePasswordHistoryTable()
    {
        require_once APP_ROOT . '/install/schema.php';
        $prefix = Config::get('db.prefix', 'cb_');
        DB::pdo()->exec(install_password_history_schema($prefix));
    }
}
