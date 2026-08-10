<?php
/**
 * 后台插件管理：列表/启用/禁用/删除/插件设置页分发（仅管理员，能力点 manage_plugins）
 */
defined('APP_BOOT') or exit;

class AdminPlugin
{
    /** 插件列表 */
    public static function listAction()
    {
        Auth::require_cap('manage_plugins');
        Admin::render('插件管理', 'plugin_list', array(
            'plugins' => Plugin::discover(),
            'actives' => Plugin::activeList(),
            'orphans' => self::orphanSlugs(),
        ));
    }

    /**
     * 检测不规范卸载（直接删文件夹）的残留 slug：目录不存在但仍有数据
     *
     * @return array slug 列表
     */
    private static function orphanSlugs()
    {
        $discovered = Plugin::discover();
        $candidates = array();
        // 残留来源一：plugin_data 表行
        $rows = DB::query('plugin_data')->select(array('plugin'));
        foreach ($rows as $r) {
            $candidates[$r['plugin']] = 1;
        }
        // 残留来源二：plugin_{slug}_* 选项（slug 仅小写字母数字连字符、不含下划线，
        // 故命名空间前缀后第一个下划线之前即完整 slug）；跳过内核自用键
        $rows = DB::query('options')
            ->where('option_key', 'LIKE', 'plugin\_%')
            ->select(array('option_key'));
        foreach ($rows as $r) {
            if ($r['option_key'] === 'plugin_data_purge_at') {
                continue;
            }
            $rest = substr($r['option_key'], 7);
            $pos = strpos($rest, '_');
            if ($pos !== false) {
                $candidates[substr($rest, 0, $pos)] = 1;
            }
        }
        $orphans = array();
        foreach (array_keys($candidates) as $slug) {
            if (!isset($discovered[$slug]) && preg_match('/^[a-z0-9-]{1,64}$/', $slug)) {
                $orphans[] = $slug;
            }
        }
        sort($orphans);
        return $orphans;
    }

    /** 清理不规范卸载的残留数据（目录必须确不存在，内核复用卸载回收逻辑） */
    public static function cleanupOrphanAction()
    {
        Auth::require_cap('manage_plugins');
        $slug = self::slugInput('post');
        if ($slug === '' || !Plugin::purgeOrphanData($slug)) {
            flash_set('error', '该插件无需清理或目录仍存在');
        } else {
            flash_set('success', '残留数据已清理：' . $slug);
        }
        redirect(site_base_admin('plugin/list'));
    }

    /** 启用插件 */
    public static function activateAction()
    {
        Auth::require_cap('manage_plugins');
        $slug = self::slugInput('post');
        if ($slug === '' || !Plugin::activate($slug)) {
            flash_set('error', '插件不存在或启用失败');
        } else {
            flash_set('success', '插件已启用');
        }
        redirect(site_base_admin('plugin/list'));
    }

    /** 禁用插件 */
    public static function deactivateAction()
    {
        Auth::require_cap('manage_plugins');
        $slug = self::slugInput('post');
        if ($slug === '' || !Plugin::deactivate($slug)) {
            flash_set('error', '插件未启用或不存在');
        } else {
            flash_set('success', '插件已禁用');
        }
        redirect(site_base_admin('plugin/list'));
    }

    /** 删除插件（前端二次确认） */
    public static function uninstallAction()
    {
        Auth::require_cap('manage_plugins');
        $slug = self::slugInput('post');
        if ($slug === '' || !Plugin::uninstall($slug)) {
            flash_set('error', '插件不存在或删除失败');
        } else {
            flash_set('success', '插件已删除');
        }
        redirect(site_base_admin('plugin/list'));
    }

    /** 插件设置页：渲染插件经 register_plugin_page 注册的回调 */
    public static function pageAction()
    {
        Auth::require_cap('manage_plugins');
        $slug = self::slugInput('get');
        // 确保插件的 admin_menu 钩子已执行（设置页注册发生在该钩子中）
        if (empty($GLOBALS['cb_admin_menu_done'])) {
            do_action('admin_menu');
            $GLOBALS['cb_admin_menu_done'] = 1;
        }
        $pages = get_plugin_pages();
        if ($slug === '' || !isset($pages[$slug]) || !Plugin::isActive($slug)) {
            Admin::forbidden();
        }
        ob_start();
        // 设置页回调执行期间置于该插件上下文：其内写入仅允许自身命名空间
        $prev = Plugin::setCurrentSlug($slug);
        call_user_func($pages[$slug]['callback']);
        Plugin::setCurrentSlug($prev);
        $contentHtml = ob_get_clean();
        Admin::render($pages[$slug]['title'], 'plugin_page', array('contentHtml' => $contentHtml));
    }

    /** 校验并读取插件 slug（防路径穿越） */
    private static function slugInput($source)
    {
        $slug = input_text('slug', '', 64, $source);
        return preg_match('/^[a-z0-9-]{1,64}$/', $slug) ? $slug : '';
    }
}
