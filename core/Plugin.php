<?php
/**
 * 插件机制：plugins/{slug}/{slug}.php 发现与元数据解析、启用状态管理、插件 API
 */
defined('APP_BOOT') or exit;

class Plugin
{
    /** @var array|null 插件元数据缓存 slug => meta */
    private static $cache = null;

    /**
     * 扫描 plugins/ 目录发现全部合法插件
     *
     * @return array slug => 元数据（name/description/version/author/has_settings）
     */
    public static function discover()
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        self::$cache = array();
        $dir = APP_ROOT . '/plugins';
        if (!is_dir($dir)) {
            return self::$cache;
        }
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $main = $dir . '/' . $item . '/' . $item . '.php';
            if (!is_file($main)) {
                continue;
            }
            $meta = self::parseMeta($main);
            if ($meta === null) {
                continue;
            }
            $meta['slug'] = $item;
            self::$cache[$item] = $meta;
        }
        return self::$cache;
    }

    /**
     * 解析插件主文件头部元数据（同 WordPress 格式）
     */
    private static function parseMeta($file)
    {
        $head = file_get_contents($file, false, null, 0, 2048);
        if ($head === false) {
            return null;
        }
        $fields = array(
            'name'        => 'Plugin Name',
            'description' => 'Description',
            'version'     => 'Version',
            'author'      => 'Author',
            'requires'    => 'Requires',
        );
        $meta = array();
        foreach ($fields as $key => $label) {
            if (preg_match('/\*\s*' . preg_quote($label, '/') . '\s*:\s*(.+)$/mi', $head, $m)) {
                $meta[$key] = trim($m[1]);
            } else {
                $meta[$key] = '';
            }
        }
        // 头部元数据不合法（缺 Plugin Name）视为非插件
        if ($meta['name'] === '') {
            return null;
        }
        return $meta;
    }

    /** 已启用插件 slug 列表 */
    public static function activeList()
    {
        return Option::getJson('active_plugins', array());
    }

    /** 是否已启用 */
    public static function isActive($slug)
    {
        return in_array($slug, self::activeList(), true);
    }

    /** 加载全部已启用插件主文件 */
    public static function loadActive()
    {
        foreach (self::activeList() as $slug) {
            $file = APP_ROOT . '/plugins/' . $slug . '/' . $slug . '.php';
            if (is_file($file)) {
                require_once $file;
            }
        }
    }

    /**
     * 启用插件
     *
     * @param string $slug 插件 slug
     * @return bool
     */
    public static function activate($slug)
    {
        $all = self::discover();
        if (!isset($all[$slug])) {
            return false;
        }
        $list = self::activeList();
        if (!in_array($slug, $list, true)) {
            $list[] = $slug;
            Option::set('active_plugins', $list);
        }
        do_action('plugin_activate', $slug);
        blog_log('plugin', 'plugin.activate', 'success', array('slug' => $slug));
        return true;
    }

    /**
     * 禁用插件
     *
     * @param string $slug 插件 slug
     * @return bool
     */
    public static function deactivate($slug)
    {
        $list = self::activeList();
        $index = array_search($slug, $list, true);
        if ($index === false) {
            return false;
        }
        unset($list[$index]);
        Option::set('active_plugins', array_values($list));
        do_action('plugin_deactivate', $slug);
        blog_log('plugin', 'plugin.deactivate', 'success', array('slug' => $slug));
        return true;
    }

    /**
     * 删除插件目录（管理页二次确认后调用）
     *
     * @param string $slug 插件 slug
     * @return bool
     */
    public static function uninstall($slug)
    {
        $dir = APP_ROOT . '/plugins/' . $slug;
        // 防路径穿越：slug 必须为纯小写字母数字连字符
        if (!preg_match('/^[a-z0-9-]{1,64}$/', $slug) || !is_dir($dir)) {
            return false;
        }
        self::deactivate($slug);
        self::removeDir($dir);
        do_action('plugin_uninstall', $slug);
        // 内核兜底清理残留数据：options 配置、plugin_data 数据、已登记自建表
        self::cleanupData($slug);
        blog_log('plugin', 'plugin.uninstall', 'success', array('slug' => $slug));
        return true;
    }

    /**
     * 卸载残留清理：plugin_{slug}_* 选项、plugin_data 中该插件的行、
     * 经 plugin_register_table() 登记的自建表（插件无需自行清理）
     *
     * @param string $slug 插件 slug
     * @return void
     */
    private static function cleanupData($slug)
    {
        try {
            // 先读自建表清单（其 option 行随后会被 LIKE 删除）
            $tables = Option::getJson('plugin_' . $slug . '_tables', array());
            // LIKE 中下划线为通配符，需反斜杠转义确保只匹配 plugin_{slug}_ 前缀
            DB::query('options')
                ->where('option_key', 'LIKE', 'plugin_' . $slug . '\\_%')
                ->delete();
            DB::delete('plugin_data', array('plugin' => $slug));
            foreach ($tables as $name) {
                // 双重白名单校验后才允许拼入 DDL，防注入
                if (preg_match('/^[a-z0-9_]{1,32}$/', $name)) {
                    DB::pdo()->exec('DROP TABLE IF EXISTS `' . plugin_table($slug, $name) . '`');
                }
            }
            Option::resetCache();
        } catch (Exception $ex) {
            error_log('[blog] plugin cleanup failed (' . $slug . '): ' . $ex->getMessage());
        }
    }

    /** 递归删除目录 */
    private static function removeDir($dir)
    {
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                self::removeDir($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}

/**
 * 读取插件配置项（options 键名 plugin_{slug}_{key}）
 *
 * @param string $slug    插件 slug
 * @param string $key     配置键
 * @param mixed  $default 缺省值
 * @return mixed
 */
function plugin_option($slug, $key, $default = null)
{
    return Option::get('plugin_' . $slug . '_' . $key, $default);
}

/**
 * 更新插件配置项
 *
 * @param string $slug  插件 slug
 * @param string $key   配置键
 * @param mixed  $value 值
 * @return void
 */
function plugin_option_update($slug, $key, $value)
{
    Option::set('plugin_' . $slug . '_' . $key, $value);
}

/**
 * 插件静态资源 URL
 *
 * @param string $slug 插件 slug
 * @param string $path 相对路径
 * @return string
 */
function plugin_url($slug, $path = '')
{
    return Router::base() . '/plugins/' . $slug . '/' . ltrim($path, '/');
}

/**
 * 注册插件后台设置页（admin_menu 钩子中调用）
 *
 * @param string   $slug     插件 slug
 * @param string   $title    菜单标题
 * @param callable $callback 渲染回调
 * @return void
 */
function register_plugin_page($slug, $title, $callback)
{
    $GLOBALS['cb_plugin_pages'][$slug] = array('title' => $title, 'callback' => $callback);
}

/**
 * 获取已注册的插件设置页
 *
 * @return array
 */
function get_plugin_pages()
{
    return isset($GLOBALS['cb_plugin_pages']) ? $GLOBALS['cb_plugin_pages'] : array();
}

/* ================= 插件通用数据存储（plugin_data 表） ================= */

/**
 * 写入插件全局数据（ttl>0 时为临时缓存，到期自动失效）
 *
 * @param string $slug  插件 slug
 * @param string $key   数据键（≤191 字符）
 * @param mixed  $value 值（数组自动 JSON 编码）
 * @param int    $ttl   存活秒数，0 表示永久
 * @return void
 */
function plugin_data_set($slug, $key, $value, $ttl = 0)
{
    plugin_data_write($slug, 'global', 0, $key, $value, $ttl);
}

/**
 * 读取插件全局数据（过期行视为不存在）
 *
 * @param string $slug    插件 slug
 * @param string $key     数据键
 * @param mixed  $default 缺省值
 * @return mixed
 */
function plugin_data_get($slug, $key, $default = null)
{
    return plugin_data_read($slug, 'global', 0, $key, $default);
}

/**
 * 删除插件全局数据
 */
function plugin_data_delete($slug, $key)
{
    DB::delete('plugin_data', array('plugin' => $slug, 'scope' => 'global', 'user_id' => 0, 'data_key' => $key));
}

/**
 * 写入插件用户级数据（替代 usermeta：第三方账号绑定等场景）
 */
function plugin_user_set($slug, $userId, $key, $value)
{
    plugin_data_write($slug, 'user', (int) $userId, $key, $value, 0);
}

/**
 * 读取插件用户级数据
 */
function plugin_user_get($slug, $userId, $key, $default = null)
{
    return plugin_data_read($slug, 'user', (int) $userId, $key, $default);
}

/**
 * 删除插件用户级数据
 */
function plugin_user_delete($slug, $userId, $key)
{
    DB::delete('plugin_data', array('plugin' => $slug, 'scope' => 'user', 'user_id' => (int) $userId, 'data_key' => $key));
}

/** 统一写入：存在则更新，不存在则插入（唯一索引兜底） */
function plugin_data_write($slug, $scope, $userId, $key, $value, $ttl)
{
    if (is_array($value)) {
        $value = json_encode($value);
    }
    $where = array('plugin' => $slug, 'scope' => $scope, 'user_id' => (int) $userId, 'data_key' => $key);
    $row = array(
        'data_value' => (string) $value,
        'expires_at' => $ttl > 0 ? date('Y-m-d H:i:s', time() + (int) $ttl) : null,
        'updated_at' => now(),
    );
    $exists = DB::query('plugin_data')->whereMap($where)->value('id');
    if ($exists !== null) {
        DB::update('plugin_data', $row, array('id' => (int) $exists));
    } else {
        DB::insert('plugin_data', array_merge($where, $row, array('created_at' => now())));
    }
}

/** 统一读取：行不存在或已过期返回缺省值 */
function plugin_data_read($slug, $scope, $userId, $key, $default)
{
    $row = DB::query('plugin_data')
        ->where('plugin', '=', $slug)
        ->where('scope', '=', $scope)
        ->where('user_id', '=', (int) $userId)
        ->where('data_key', '=', $key)
        ->first();
    if ($row === null) {
        return $default;
    }
    if ($row['expires_at'] !== null && strtotime($row['expires_at']) < time()) {
        return $default;
    }
    return $row['data_value'];
}

/**
 * 过期临时数据清理（每日最多一次，由 bootstrap 惰性触发）
 *
 * @return void
 */
function plugin_data_purge_expired()
{
    $last = (int) Option::get('plugin_data_purge_at', 0);
    if (time() - $last < 86400) {
        return;
    }
    Option::set('plugin_data_purge_at', time());
    try {
        // expires_at 为 NULL 的行不会命中 < 比较，永久数据不受影响
        DB::query('plugin_data')->where('expires_at', '<', now())->delete();
    } catch (Exception $ex) {
        error_log('[blog] plugin_data purge failed: ' . $ex->getMessage());
    }
}

/* ================= 插件自建表（复杂场景才用，卸载自动 DROP） ================= */

/**
 * 登记插件自建表：表名限定 [a-z0-9_]{1,32}，卸载时内核自动 DROP
 *
 * @param string $slug 插件 slug
 * @param string $name 表短名（不含前缀）
 * @return bool
 */
function plugin_register_table($slug, $name)
{
    if (!preg_match('/^[a-z0-9_]{1,32}$/', $name)) {
        return false;
    }
    $key = 'plugin_' . $slug . '_tables';
    $tables = Option::getJson($key, array());
    if (!in_array($name, $tables, true)) {
        $tables[] = $name;
        Option::set($key, $tables);
    }
    return true;
}

/**
 * 插件自建表完整表名（含前缀，slug 中连字符转下划线以满足标识符规则）
 *
 * @param string $slug 插件 slug
 * @param string $name 表短名
 * @return string
 */
function plugin_table($slug, $name)
{
    return DB::table('plugin_' . str_replace('-', '_', $slug) . '_' . $name);
}
