<?php
/**
 * 后台模板管理：列表/启用/删除/zip 上传（仅管理员，能力点 manage_themes）
 */
defined('APP_BOOT') or exit;

class AdminTheme
{
    /** 主题列表 */
    public static function listAction()
    {
        Auth::require_cap('manage_themes');
        Admin::render('模板管理', 'theme_list', array(
            'themes' => Theme::discover(),
            'active' => Theme::current(),
        ));
    }

    /** 启用主题 */
    public static function activateAction()
    {
        Auth::require_cap('manage_themes');
        $dir = input_text('dir', '', 64, 'post');
        $themes = Theme::discover();
        if (!preg_match('/^[a-z0-9_-]{1,64}$/', $dir) || !isset($themes[$dir])) {
            flash_set('error', '主题不存在');
        } else {
            Option::set('active_theme', $dir);
            blog_log('template', 'theme.activate', 'success', array('theme' => $dir));
            flash_set('success', '已启用主题：' . $themes[$dir]['name']);
        }
        redirect(site_base_admin('theme/list'));
    }

    /** 删除主题（默认主题与当前启用主题不可删） */
    public static function deleteAction()
    {
        Auth::require_cap('manage_themes');
        $dir = input_text('dir', '', 64, 'post');
        if (!preg_match('/^[a-z0-9_-]{1,64}$/', $dir) || $dir === 'default') {
            flash_set('error', '该主题不可删除');
            redirect(site_base_admin('theme/list'));
        }
        if ($dir === Theme::current()) {
            flash_set('error', '不能删除当前启用的主题');
            redirect(site_base_admin('theme/list'));
        }
        $path = Theme::dirOf($dir);
        if (is_dir($path)) {
            self::removeDir($path);
            blog_log('template', 'theme.delete', 'success', array('theme' => $dir));
            flash_set('success', '主题已删除');
        } else {
            flash_set('error', '主题目录不存在');
        }
        redirect(site_base_admin('theme/list'));
    }

    /** 主题设置页（表单由主题 settings.php 清单驱动，存 options.theme_settings_{dir}） */
    public static function settingAction()
    {
        Auth::require_cap('manage_themes');
        $dir = input_text('dir', '', 64, 'get');
        $themes = Theme::discover();
        if (!preg_match('/^[a-z0-9_-]{1,64}$/', $dir) || !isset($themes[$dir])) {
            flash_set('error', '主题不存在');
            redirect(site_base_admin('theme/list'));
        }
        Admin::render('主题设置', 'theme_setting', array(
            'dir'      => $dir,
            'name'     => $themes[$dir]['name'],
            'schema'   => Theme::settingsSchema($dir),
            'settings' => Theme::settingsOf($dir),
        ));
    }

    /** 保存主题设置：仅接收清单内字段，按声明类型走对应输入校验器 */
    public static function settingSaveAction()
    {
        Auth::require_cap('manage_themes');
        $dir = input_text('dir', '', 64, 'post');
        $themes = Theme::discover();
        if (!preg_match('/^[a-z0-9_-]{1,64}$/', $dir) || !isset($themes[$dir])) {
            flash_set('error', '主题不存在');
            redirect(site_base_admin('theme/list'));
        }
        $schema = Theme::settingsSchema($dir);
        $settings = array();
        foreach ($schema as $key => $field) {
            if ($field['type'] === 'textarea') {
                $settings[$key] = input_longtext($key, $field['default']);
            } elseif ($field['type'] === 'checkbox') {
                $settings[$key] = input_int($key, 0, 'post') === 1 ? '1' : '0';
            } elseif ($field['type'] === 'select') {
                $allowed = array_keys($field['options']);
                $settings[$key] = input_enum($key, $allowed, $field['default'], 'post');
            } else {
                $settings[$key] = input_text($key, $field['default'], $field['maxlength'], 'post');
            }
        }
        Option::set('theme_settings_' . $dir, json_encode($settings, JSON_UNESCAPED_UNICODE));
        blog_log('template', 'theme.setting', 'success', array('theme' => $dir));
        flash_set('success', '主题设置已保存');
        redirect(site_base_admin('theme/setting&dir=' . rawurlencode($dir)));
    }

    /** 上传主题 zip（需服务器启用 ZipArchive 扩展） */
    public static function uploadAction()
    {
        Auth::require_cap('manage_themes');
        if (empty($_FILES['theme_zip']) || (int) $_FILES['theme_zip']['error'] !== UPLOAD_ERR_OK) {
            flash_set('error', '请选择要上传的 zip 文件');
            redirect(site_base_admin('theme/list'));
        }
        $file = $_FILES['theme_zip'];
        if ((int) $file['size'] > 10 * 1024 * 1024) {
            flash_set('error', 'zip 文件不得超过 10MB');
            redirect(site_base_admin('theme/list'));
        }
        $name = strtolower($file['name']);
        if (substr($name, -4) !== '.zip') {
            flash_set('error', '仅支持 .zip 格式');
            redirect(site_base_admin('theme/list'));
        }
        if (!class_exists('ZipArchive')) {
            flash_set('error', '服务器未启用 ZipArchive 扩展，无法解压主题包');
            redirect(site_base_admin('theme/list'));
        }

        $target = preg_replace('/[^a-z0-9_-]/', '', substr($name, 0, -4));
        if ($target === '' || $target === 'default' || is_dir(Theme::dirOf($target))) {
            flash_set('error', '主题目录名非法或已存在');
            redirect(site_base_admin('theme/list'));
        }

        $zip = new ZipArchive();
        if ($zip->open($file['tmp_name']) !== true) {
            flash_set('error', 'zip 文件无法打开');
            redirect(site_base_admin('theme/list'));
        }
        // 安全校验：禁止路径穿越条目，且包内必须含 style.css
        $hasStyle = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if (strpos($entry, '..') !== false || substr($entry, 0, 1) === '/' || strpos($entry, '\\') !== false) {
                $zip->close();
                flash_set('error', 'zip 包含非法路径条目');
                redirect(site_base_admin('theme/list'));
            }
            // 条目白名单：拒绝隐藏文件（.htaccess/.user.ini 等，防宝塔下重写失效后被直接执行）
            // 与 phar/phtml/php3+ 等可执行伪装；注意不能拒 .php —— 主题模板本身就是 PHP 文件，
            // 直接执行防护由重写规则（.htaccess/nginx.conf.example/bt-panel.rewrite.conf）承担
            $base = basename($entry);
            if ($base === '' || substr($base, 0, 1) === '.' || preg_match('/\.(phar|phtml|php\d)$/i', $base)) {
                $zip->close();
                flash_set('error', 'zip 包含不允许的条目：' . $base);
                redirect(site_base_admin('theme/list'));
            }
            // 拒绝符号链接等 Unix 特殊类型条目：extractTo 会忠实重建 symlink，
            // 后续普通条目可跟随链接落到主题目录之外（Zip Slip 变种）
            $opsys = 0;
            $extAttr = 0;
            if ($zip->getExternalAttributesIndex($i, $opsys, $extAttr) && $opsys === ZipArchive::OPSYS_UNIX) {
                // 外部属性高 16 位为 st_mode；0x8000=普通文件、0x4000=目录，其余（含 0xA000 符号链接）一律拒绝
                $entryType = ((int) $extAttr >> 16) & 0xF000;
                if ($entryType !== 0 && $entryType !== 0x8000 && $entryType !== 0x4000) {
                    $zip->close();
                    flash_set('error', 'zip 包含不允许的条目类型（符号链接等）：' . $base);
                    redirect(site_base_admin('theme/list'));
                }
            }
            if (preg_match('#(^|/)style\.css$#', $entry)) {
                $hasStyle = true;
            }
        }
        if (!$hasStyle) {
            $zip->close();
            flash_set('error', '主题包缺少 style.css');
            redirect(site_base_admin('theme/list'));
        }

        $dest = Theme::dirOf($target);
        if (!is_dir($dest) && !mkdir($dest, 0755, true)) {
            $zip->close();
            flash_set('error', '主题目录创建失败');
            redirect(site_base_admin('theme/list'));
        }
        // 解压失败整体清理目标目录，避免遗留半成品文件
        if (!$zip->extractTo($dest)) {
            $zip->close();
            self::removeDir($dest);
            flash_set('error', 'zip 解压失败');
            redirect(site_base_admin('theme/list'));
        }
        $zip->close();
        // 兼容“包内再套一层目录”的打包方式：将子目录内容上移
        self::flattenSingleSubdir($dest);
        blog_log('template', 'theme.upload', 'success', array('theme' => $target));
        flash_set('success', '主题已上传：' . $target);
        redirect(site_base_admin('theme/list'));
    }

    /** 若解压结果只有一个子目录且 style.css 在其中，则把内容上移一层 */
    private static function flattenSingleSubdir($dir)
    {
        if (is_file($dir . '/style.css')) {
            return;
        }
        $items = array_diff(scandir($dir), array('.', '..'));
        if (count($items) !== 1) {
            return;
        }
        $sub = $dir . '/' . reset($items);
        if (!is_dir($sub) || !is_file($sub . '/style.css')) {
            return;
        }
        foreach (array_diff(scandir($sub), array('.', '..')) as $item) {
            rename($sub . '/' . $item, $dir . '/' . $item);
        }
        rmdir($sub);
    }

    /** 递归删除目录 */
    private static function removeDir($dir)
    {
        foreach (array_diff(scandir($dir), array('.', '..')) as $item) {
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
