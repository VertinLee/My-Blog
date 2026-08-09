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
        blog_log('plugin', 'plugin.uninstall', 'success', array('slug' => $slug));
        return true;
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
