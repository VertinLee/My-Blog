<?php
/**
 * 后台调度器：?m={module}/{action} 分发、登录守卫、密码过期拦截、布局渲染
 */
defined('APP_BOOT') or exit;

class Admin
{
    /** 模块 → 处理类映射 */
    private static $modules = array(
        'dashboard' => 'Admin',
        'post'      => 'AdminPost',
        'comment'   => 'AdminComment',
        'category'  => 'AdminCategory',
        'user'      => 'AdminUser',
        'setting'   => 'AdminSetting',
        'theme'     => 'AdminTheme',
        'plugin'    => 'AdminPlugin',
        'log'       => 'AdminLog',
        'profile'   => 'AdminProfile',
        'upload'    => 'AdminUpload',
        'auth'      => 'Admin',
    );

    /**
     * 后台请求分发入口
     *
     * @return void
     */
    public static function handle()
    {
        $m = isset($_GET['m']) && is_string($_GET['m']) ? $_GET['m'] : 'dashboard';
        if (!preg_match('#^[a-z]+(/[a-z_]+)?$#', $m)) {
            $m = 'dashboard';
        }
        $parts = explode('/', $m);
        $module = $parts[0];
        $action = isset($parts[1]) ? $parts[1] : 'index';

        // 登出
        if ($module === 'auth' && $action === 'logout') {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                Csrf::verifyOrDie();
                Auth::logout();
            }
            redirect(Router::url('login'));
        }

        // 登录守卫：未登录一律回前台登录页
        if (!Auth::check()) {
            redirect(Router::url('login'));
        }

        // 密码过期强制改密拦截（开启后无绕过路径：仅放行改密页、改密提交与登出）
        $isPasswordFlow = $module === 'profile' && in_array($action, array('password', 'password_save'), true);
        if (!empty($_SESSION['pwd_expired']) && !$isPasswordFlow) {
            flash_set('error', '密码已过期，请先修改密码');
            redirect(site_base_admin('profile/password'));
        }

        if (!isset(self::$modules[$module])) {
            self::forbidden('unknown module');
        }
        $class = self::$modules[$module];
        // URL 动作为 snake_case（如 site_save），方法名为 camelCase（如 siteSaveAction），必须转换
        $method = str_replace(' ', '', ucwords(str_replace('_', ' ', $action))) . 'Action';
        if (!method_exists($class, $method)) {
            // 常见于服务器端核心文件未同步或入口漏加载处理类
            self::forbidden('unknown action');
        }

        // 所有 POST 动作统一 CSRF 校验
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verifyOrDie();
        }

        call_user_func(array($class, $method));
    }

    /** 403 终止（reason 仅写入审计日志，页面输出保持通用文案不泄露细节） */
    public static function forbidden($reason = '')
    {
        http_response_code(403);
        $detail = array('uri' => mb_substr($_SERVER['REQUEST_URI'], 0, 200));
        if ($reason !== '') {
            $detail['reason'] = $reason;
        }
        blog_log('security', 'admin.forbidden', 'fail', $detail);
        exit('403 Forbidden: invalid request');
    }

    /** 仪表盘 */
    public static function indexAction()
    {
        // 全站统计属管理信息：仅管理员计算与可见，user/editor 不暴露（最小信息原则）
        $stats = array();
        $recentLogs = array();
        if (Auth::isAdmin()) {
            $stats = array(
                'posts'     => DB::query('posts')->where('is_page', '=', 0)->count(),
                'published' => DB::query('posts')->where('status', '=', 'published')->where('is_page', '=', 0)->count(),
                'comments'  => DB::query('comments')->where('status', '=', 'published')->count(),
                'pendingComments' => DB::query('comments')->where('status', '=', 'pending')->count(),
                'users'     => DB::query('users')->count(),
                'pendingPosts' => DB::query('posts')->where('status', '=', 'pending')->count(),
            );
            // 审计日志属敏感数据，仅具备 view_logs 能力点（管理员）才可查看预览
            if (Auth::check_cap('view_logs')) {
                $recentLogs = DB::query('logs')->orderBy('id', 'DESC')->limit(10)->select();
            }
        }
        self::render('仪表盘', 'dashboard', array('stats' => $stats, 'recentLogs' => $recentLogs));
    }

    /**
     * 后台菜单定义（按能力点动态显隐）
     *
     * @return array
     */
    public static function menu()
    {
        $items = array(
            array('m' => 'dashboard', 'name' => '仪表盘', 'cap' => 'read'),
            array('m' => 'post/list', 'name' => '文章管理', 'cap' => 'edit_posts'),
            array('m' => 'comment/list', 'name' => '评论管理', 'cap' => 'manage_comments'),
            array('m' => 'category/list', 'name' => '分类管理', 'cap' => 'manage_categories'),
            array('m' => 'user/list', 'name' => '用户管理', 'cap' => 'manage_users'),
            array('m' => 'setting/site', 'name' => '站点设置', 'cap' => 'manage_options'),
            array('m' => 'setting/nav', 'name' => '导航管理', 'cap' => 'manage_options'),
            array('m' => 'setting/security', 'name' => '安全设置', 'cap' => 'manage_security'),
            array('m' => 'theme/list', 'name' => '模板管理', 'cap' => 'manage_themes'),
            array('m' => 'plugin/list', 'name' => '插件管理', 'cap' => 'manage_plugins'),
            array('m' => 'log/list', 'name' => '日志中心', 'cap' => 'view_logs'),
            array('m' => 'profile', 'name' => '个人资料', 'cap' => 'edit_profile'),
        );
        $visible = array();
        $pluginGroupIndex = -1;
        foreach ($items as $item) {
            if (Auth::check_cap($item['cap'])) {
                $visible[] = $item;
                // 记录插件管理的位置，插件设置二级菜单紧随其后
                if ($item['m'] === 'plugin/list') {
                    $pluginGroupIndex = count($visible) - 1;
                }
            }
        }
        // 插件设置页经 admin_menu 钩子注册，收进「插件」二级菜单，避免侧边栏随插件数量无限变长；
        // 无启用插件（无设置页注册）时不显示该菜单
        do_action('admin_menu');
        $pluginChildren = array();
        if (Auth::check_cap('manage_plugins')) {
            foreach (get_plugin_pages() as $slug => $page) {
                $pluginChildren[] = array('m' => 'plugin/page&slug=' . $slug, 'name' => $page['title'], 'cap' => 'manage_plugins', 'plugin' => true);
            }
        }
        if (!empty($pluginChildren)) {
            $group = array('name' => '插件', 'children' => $pluginChildren);
            if ($pluginGroupIndex >= 0) {
                array_splice($visible, $pluginGroupIndex + 1, 0, array($group));
            } else {
                $visible[] = $group;
            }
        }
        return $visible;
    }

    /**
     * 渲染后台页面（统一布局：左侧菜单 + 顶栏）
     *
     * @param string $title 页面标题
     * @param string $view  视图文件名（user/views/ 下）
     * @param array  $data  视图数据
     * @return void
     */
    public static function render($title, $view, array $data = array())
    {
        $data['pageTitle'] = $title;
        $data['menuItems'] = self::menu();
        $data['currentM'] = isset($_GET['m']) ? $_GET['m'] : 'dashboard';
        extract($data, EXTR_SKIP);
        include APP_ROOT . '/user/views/layout.php';
    }
}
