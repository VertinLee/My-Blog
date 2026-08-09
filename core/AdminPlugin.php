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
        ));
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
        call_user_func($pages[$slug]['callback']);
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
