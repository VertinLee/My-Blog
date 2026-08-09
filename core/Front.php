<?php
/**
 * 前台控制器：伪静态路由分发、认证表单、评论提交、验证码收发
 */
defined('APP_BOOT') or exit;

class Front
{
    /**
     * 前台请求分发入口
     *
     * @return void
     */
    public static function handle()
    {
        $route = Router::parse();
        $name = $route['route'];
        $params = $route['params'];

        switch ($name) {
            case 'home':
                self::listing('home', $params['page'], array(), '');
                break;
            case 'category':
                self::category($params);
                break;
            case 'author':
                self::author($params);
                break;
            case 'search':
                self::search();
                break;
            case 'post':
                self::post($params['key']);
                break;
            case 'page':
                self::singlePage($params['slug']);
                break;
            case 'login':
                self::login();
                break;
            case 'register':
                self::register();
                break;
            case 'forgot':
                self::forgot();
                break;
            case 'logout':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    Csrf::verifyOrDie();
                    Auth::logout();
                }
                redirect(Router::url('home'));
                break;
            case 'comment_save':
                self::commentSave();
                break;
            case 'comment_update':
                self::commentUpdate();
                break;
            case 'comment_delete':
                self::commentDelete();
                break;
            case 'verify_send':
                self::verifySend();
                break;
            case 'verify_check':
                self::verifyCheck();
                break;
            default:
                self::notFound();
        }
    }

    /**
     * 通用文章列表渲染
     */
    private static function listing($route, $page, array $extraWhere, $title)
    {
        $perPage = max(1, (int) Option::get('posts_per_page', 10));
        $query = DB::query('posts')
            ->where('status', '=', 'published')
            ->where('is_page', '=', 0);
        foreach ($extraWhere as $w) {
            $query->where($w[0], $w[1], $w[2]);
        }
        $total = $query->count();
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $totalPages);

        $q = DB::query('posts')
            ->where('status', '=', 'published')
            ->where('is_page', '=', 0);
        foreach ($extraWhere as $w) {
            $q->where($w[0], $w[1], $w[2]);
        }
        $posts = $q->orderBy('created_at', 'DESC')
            ->limit($perPage, ($page - 1) * $perPage)
            ->select();
        self::attachAuthors($posts);

        Theme::render('index', array(
            'page_type'  => 'index',
            'title'      => $title,
            'posts'      => $posts,
            'page'       => $page,
            'totalPages' => $totalPages,
            'route'      => $route,
            'routeParams' => $extraWhere ? array() : array(),
        ));
    }

    /**
     * 注销用户前台展示匿名化：用户名/昵称统一显示“用户已注销”，头像置空
     * （模板侧 avatar_url 遇空头像自动回退默认头像）；数据不删除，仅展示层匿名
     *
     * @param array $author 用户行（需含 is_deleted）
     * @return array
     */
    private static function maskDeletedAuthor(array $author)
    {
        if (!empty($author['is_deleted'])) {
            $author['nickname'] = '用户已注销';
            if (isset($author['username'])) {
                $author['username'] = '用户已注销';
            }
            $author['avatar'] = '';
        }
        return $author;
    }

    /** 为文章列表附加作者信息 */
    private static function attachAuthors(array &$posts)
    {
        if (empty($posts)) {
            return;
        }
        $ids = array();
        foreach ($posts as $post) {
            $ids[] = (int) $post['author_id'];
        }
        $users = DB::query('users')->whereIn('id', array_unique($ids))
            ->select(array('id', 'username', 'nickname', 'avatar', 'is_deleted'));
        $map = array();
        foreach ($users as $u) {
            $map[(int) $u['id']] = self::maskDeletedAuthor($u);
        }
        // 分类名映射
        $cats = DB::query('categories')->select(array('id', 'name', 'slug'));
        $catMap = array();
        foreach ($cats as $c) {
            $catMap[(int) $c['id']] = $c;
        }
        foreach ($posts as &$post) {
            $aid = (int) $post['author_id'];
            $post['author'] = isset($map[$aid]) ? $map[$aid] : array('nickname' => '未知', 'id' => 0, 'avatar' => '');
            $cid = (int) $post['category_id'];
            $post['category'] = isset($catMap[$cid]) ? $catMap[$cid] : null;
        }
        unset($post);
    }

    /** 分类归档 */
    private static function category(array $params)
    {
        $cat = DB::query('categories')->where('slug', '=', $params['slug'])->first();
        if (!$cat) {
            self::notFound();
            return;
        }
        self::renderArchive('category', $params['page'], array(
            array('category_id', '=', (int) $cat['id']),
        ), '分类：' . $cat['name'], array('slug' => $cat['slug']), $cat);
    }

    /** 作者归档 */
    private static function author(array $params)
    {
        $user = DB::query('users')->where('id', '=', $params['id'])->first(array('id', 'username', 'nickname', 'avatar', 'is_deleted'));
        if (!$user) {
            self::notFound();
            return;
        }
        $user = self::maskDeletedAuthor($user);
        self::renderArchive('author', $params['page'], array(
            array('author_id', '=', (int) $user['id']),
        ), '作者：' . $user['nickname'], array('id' => (int) $user['id']), $user);
    }

    /** 归档列表渲染（分类/作者共用） */
    private static function renderArchive($route, $page, array $extraWhere, $title, array $routeParams, $subject)
    {
        $perPage = max(1, (int) Option::get('posts_per_page', 10));
        $query = DB::query('posts')
            ->where('status', '=', 'published')
            ->where('is_page', '=', 0);
        foreach ($extraWhere as $w) {
            $query->where($w[0], $w[1], $w[2]);
        }
        $total = $query->count();
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $totalPages);

        $q = DB::query('posts')
            ->where('status', '=', 'published')
            ->where('is_page', '=', 0);
        foreach ($extraWhere as $w) {
            $q->where($w[0], $w[1], $w[2]);
        }
        $posts = $q->orderBy('created_at', 'DESC')
            ->limit($perPage, ($page - 1) * $perPage)
            ->select();
        self::attachAuthors($posts);

        Theme::render('archive', array(
            'page_type'   => 'archive',
            'title'       => $title,
            'posts'       => $posts,
            'page'        => $page,
            'totalPages'  => $totalPages,
            'route'       => $route,
            'routeParams' => $routeParams,
            'subject'     => $subject,
        ));
    }

    /** 搜索 */
    private static function search()
    {
        $kw = input_text('q', '', 50, 'get');
        $page = input_int('page', 1, 'get');
        $perPage = max(1, (int) Option::get('posts_per_page', 10));
        $posts = array();
        $total = 0;
        $totalPages = 1;
        if ($kw !== '') {
            $query = DB::query('posts')
                ->where('status', '=', 'published')
                ->where('is_page', '=', 0)
                ->likeAny(array('title', 'content', 'excerpt'), $kw);
            $total = $query->count();
            $totalPages = max(1, (int) ceil($total / $perPage));
            $page = min(max(1, $page), $totalPages);
            $q = DB::query('posts')
                ->where('status', '=', 'published')
                ->where('is_page', '=', 0)
                ->likeAny(array('title', 'content', 'excerpt'), $kw);
            $posts = $q->orderBy('created_at', 'DESC')
                ->limit($perPage, ($page - 1) * $perPage)
                ->select();
            self::attachAuthors($posts);
        }
        Theme::render('search', array(
            'page_type'   => 'search',
            'title'       => '搜索',
            'posts'       => $posts,
            'page'        => $page,
            'totalPages'  => $totalPages,
            'route'       => 'search',
            'routeParams' => array('q' => $kw),
            'kw'          => $kw,
            'total'       => $total,
        ));
    }

    /** 文章详情 */
    private static function post($key)
    {
        $query = DB::query('posts');
        if (ctype_digit($key)) {
            $post = $query->where('id', '=', (int) $key)->first();
        } else {
            $post = $query->where('slug', '=', $key)->first();
        }
        // 仅 published 对游客可见；作者与管理员可预览非删除状态文章
        if (!$post || $post['status'] === 'trash') {
            self::notFound();
            return;
        }
        if ($post['status'] !== 'published') {
            $allowed = Auth::isAdmin() || (Auth::check() && Auth::id() === (int) $post['author_id']);
            if (!$allowed) {
                self::notFound();
                return;
            }
        }

        // 浏览数 +1
        DB::query('posts')->where('id', '=', (int) $post['id'])
            ->update(array('views' => (int) $post['views'] + 1));

        $author = DB::query('users')->where('id', '=', (int) $post['author_id'])
            ->first(array('id', 'username', 'nickname', 'avatar', 'is_deleted'));
        if ($author) {
            $author = self::maskDeletedAuthor($author);
        }
        $category = null;
        if ((int) $post['category_id'] > 0) {
            $category = DB::query('categories')->where('id', '=', (int) $post['category_id'])->first();
        }
        $post['author'] = $author ? $author : array('nickname' => '未知', 'id' => 0, 'avatar' => '');
        $post['category'] = $category;

        // 评论：游客只见 published；作者本人可见自己的 pending
        $commentQuery = DB::query('comments')
            ->where('post_id', '=', (int) $post['id'])
            ->where('status', '=', 'published');
        $comments = $commentQuery->orderBy('created_at', 'ASC')->select();
        if (Auth::check()) {
            $mine = DB::query('comments')
                ->where('post_id', '=', (int) $post['id'])
                ->where('user_id', '=', Auth::id())
                ->where('status', '=', 'pending')
                ->orderBy('created_at', 'ASC')
                ->select();
            $comments = array_merge($comments, $mine);
        }
        $commentUsers = array();
        $uids = array();
        foreach ($comments as $c) {
            $uids[] = (int) $c['user_id'];
        }
        if (!empty($uids)) {
            $rows = DB::query('users')->whereIn('id', array_unique($uids))
                ->select(array('id', 'nickname', 'avatar', 'is_deleted'));
            foreach ($rows as $r) {
                $commentUsers[(int) $r['id']] = self::maskDeletedAuthor($r);
            }
        }
        foreach ($comments as &$c) {
            $uid = (int) $c['user_id'];
            $c['author'] = isset($commentUsers[$uid]) ? $commentUsers[$uid] : array('nickname' => '用户', 'avatar' => '');
        }
        unset($c);

        // 前台文章页输出受限 CSP（仅允许 self 本地化资源）
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; font-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'self'");

        // 评论行内编辑入口（?edit_comment={id}）：模板侧仅对本人评论渲染编辑表单，服务端提交时再次校验
        $editCommentId = Auth::check() ? input_int('edit_comment', 0, 'get') : 0;

        Theme::render('single', array(
            'page_type' => 'single',
            'title'     => $post['title'],
            'post'      => $post,
            'comments'  => $comments,
            'editCommentId' => $editCommentId,
        ));
    }

    /** 独立页面 */
    private static function singlePage($slug)
    {
        $post = DB::query('posts')
            ->where('slug', '=', $slug)
            ->where('is_page', '=', 1)
            ->where('status', '=', 'published')
            ->first();
        if (!$post) {
            self::notFound();
            return;
        }
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; font-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'self'");
        Theme::render('page', array(
            'page_type' => 'page',
            'title'     => $post['title'],
            'post'      => $post,
        ));
    }

    /** 登录页（GET 渲染 / POST 提交） */
    private static function login()
    {
        if (Auth::check()) {
            redirect(site_base_admin());
        }
        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verifyOrDie();
            $account = input_text('account', '', 128, 'post');
            $password = isset($_POST['password']) ? (string) $_POST['password'] : '';
            if ($account === '' || $password === '') {
                $error = '请输入账号和密码';
            } else {
                $result = Auth::attempt($account, $password);
                if ($result['ok']) {
                    redirect(site_base_admin());
                }
                $error = $result['msg'];
            }
        }
        Theme::render('login', array(
            'page_type' => 'login',
            'title'     => '登录',
            'error'     => $error,
            'account'   => isset($_POST['account']) ? input_text('account', '', 128, 'post') : '',
        ));
    }

    /** 注册页 */
    private static function register()
    {
        // 管理员可在后台关闭公开注册；GET/POST 均在此拦截
        if (Option::get('register_disabled', '0') === '1') {
            flash_set('error', '本站已关闭公开注册，如需账号请联系管理员');
            redirect(Router::url('login'));
        }
        if (Auth::check()) {
            redirect(site_base_admin());
        }
        $emailEnabled = Plugin::isActive('smtp-mailer');
        $smsEnabled = Plugin::isActive('aliyun-sms');
        $error = '';
        $old = array('username' => '', 'nickname' => '', 'email' => '', 'phone' => '');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verifyOrDie();
            $username = input_text('username', '', 32, 'post');
            $nickname = input_text('nickname', '', 64, 'post');
            $email = input_email('email', '', 'post');
            $phone = input_phone('phone', '', 'post');
            $password = isset($_POST['password']) ? (string) $_POST['password'] : '';
            $password2 = isset($_POST['password2']) ? (string) $_POST['password2'] : '';
            $old = array('username' => $username, 'nickname' => $nickname, 'email' => $email, 'phone' => $phone);

            do {
                if (!preg_match('/^[a-zA-Z0-9_]{3,32}$/', $username)) {
                    $error = '用户名为 3-32 位字母、数字或下划线';
                    break;
                }
                if (DB::query('users')->where('username', '=', $username)->value('id')) {
                    $error = '用户名已被占用';
                    break;
                }
                if ($email !== '' && DB::query('users')->where('email', '=', $email)->value('id')) {
                    $error = '邮箱已被注册';
                    break;
                }
                // 手机号作为身份核验凭据，必须全局唯一，防止一号多绑
                if ($phone !== '' && DB::query('users')->where('phone', '=', $phone)->value('id')) {
                    $error = '该手机号已被注册';
                    break;
                }
                if ($password !== $password2) {
                    $error = '两次输入的密码不一致';
                    break;
                }
                $pwdErr = Auth::validate_password_strength($password, $username);
                if ($pwdErr !== '') {
                    $error = $pwdErr;
                    break;
                }
                // 验证码核验（启用对应插件时才要求）
                if ($emailEnabled) {
                    $code = input_text('email_code', '', 6, 'post');
                    if ($email === '' || !self::checkVerifyCode('register', $email, $code, 'email')) {
                        $error = '邮箱验证码错误或已过期';
                        break;
                    }
                }
                if ($smsEnabled) {
                    // 短信插件启用时手机号必填且必须通过验证码核验
                    if ($phone === '') {
                        $error = '请输入手机号';
                        break;
                    }
                    $code = input_text('sms_code', '', 6, 'post');
                    if (!self::checkVerifyCode('register', $phone, $code, 'sms')) {
                        $error = '短信验证码错误或已过期';
                        break;
                    }
                }

                // 公开注册只能产生 role=user（功能红线）
                $userId = DB::insert('users', array(
                    'username'            => $username,
                    'nickname'            => $nickname !== '' ? $nickname : $username,
                    'password'            => password_hash($password, PASSWORD_DEFAULT),
                    'email'               => $email !== '' ? $email : null,
                    'phone'               => $phone,
                    'avatar'              => '',
                    'role'                => 'user',
                    'status'              => 1,
                    'password_changed_at' => now(),
                    'login_fail'          => 0,
                    'locked_until'        => null,
                    'created_at'          => now(),
                ));
                // 标记验证码已使用
                if ($emailEnabled && $email !== '') {
                    self::markCodeUsed('register', $email, 'email');
                }
                if ($smsEnabled && $phone !== '') {
                    self::markCodeUsed('register', $phone, 'sms');
                }
                blog_log('user', 'user.register', 'success', array('user_id' => $userId, 'username' => $username));
                do_action('user_register', $userId, array(
                    'username' => $username, 'email' => $email, 'phone' => $phone,
                ));
                flash_set('success', '注册成功，请登录');
                redirect(Router::url('login'));
            } while (false);
        }

        Theme::render('register', array(
            'page_type'    => 'register',
            'title'        => '注册',
            'error'        => $error,
            'old'          => $old,
            'emailEnabled' => $emailEnabled,
            'smsEnabled'   => $smsEnabled,
        ));
    }

    /** 找回密码页（两步：发送验证码 → 核验并设新密码） */
    private static function forgot()
    {
        if (Auth::check()) {
            redirect(site_base_admin());
        }
        $emailEnabled = Plugin::isActive('smtp-mailer');
        $smsEnabled = Plugin::isActive('aliyun-sms');
        $error = '';
        $info = '';
        if (!$emailEnabled && !$smsEnabled) {
            Theme::render('forgot', array(
                'page_type'    => 'forgot',
                'title'        => '找回密码',
                'error'        => '',
                'info'         => '站点未启用邮箱或短信验证插件，请联系管理员重置密码。',
                'emailEnabled' => false,
                'smsEnabled'   => false,
            ));
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verifyOrDie();
            $account = input_text('account', '', 128, 'post');
            $user = null;
            if ($account !== '') {
                if (strpos($account, '@') !== false) {
                    $user = DB::query('users')->where('email', '=', $account)->first();
                } else {
                    $user = DB::query('users')->where('username', '=', $account)->first();
                }
            }
            $code = input_text('code', '', 6, 'post');
            $newPassword = isset($_POST['new_password']) ? (string) $_POST['new_password'] : '';
            $newPassword2 = isset($_POST['new_password2']) ? (string) $_POST['new_password2'] : '';

            do {
                if (!$user) {
                    // 不暴露账号是否存在，统一提示
                    $error = '若账号存在且信息正确，密码将被重置';
                    break;
                }
                $target = strpos($account, '@') !== false ? $user['email'] : (!empty($user['phone']) ? $user['phone'] : $user['email']);
                $channel = strpos($account, '@') !== false ? 'email' : (!empty($user['phone']) && $smsEnabled ? 'sms' : 'email');
                if (empty($target)) {
                    $error = '该账号未绑定邮箱或手机，请联系管理员';
                    break;
                }
                if ($newPassword !== $newPassword2) {
                    $error = '两次输入的新密码不一致';
                    break;
                }
                $pwdErr = Auth::validate_password_strength($newPassword, $user['username']);
                if ($pwdErr !== '') {
                    $error = $pwdErr;
                    break;
                }
                if (!self::checkVerifyCode('reset', $target, $code, $channel)) {
                    $error = '验证码错误或已过期';
                    break;
                }
                $result = Auth::changePassword((int) $user['id'], null, $newPassword);
                if (!$result['ok']) {
                    $error = $result['msg'];
                    break;
                }
                self::markCodeUsed('reset', $target, $channel);
                blog_log('auth', 'password.reset', 'success', array('user_id' => (int) $user['id']));
                do_action('password_reset', (int) $user['id']);
                flash_set('success', '密码已重置，请使用新密码登录');
                redirect(Router::url('login'));
            } while (false);
        }

        Theme::render('forgot', array(
            'page_type'    => 'forgot',
            'title'        => '找回密码',
            'error'        => $error,
            'info'         => $info,
            'emailEnabled' => $emailEnabled,
            'smsEnabled'   => $smsEnabled,
        ));
    }

    /** 评论提交（POST + CSRF + 登录） */
    private static function commentSave()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect(Router::url('home'));
        }
        Csrf::verifyOrDie();
        if (!Auth::check()) {
            flash_set('error', '请先登录后再发表评论');
            redirect(Router::url('login'));
        }
        Auth::require_cap('comment');

        $postId = input_int('post_id', 0, 'post');
        $parentId = input_int('parent_id', 0, 'post');
        $content = input_text('content', '', 2000, 'post');

        $back = Router::url('home');
        if (isset($_POST['redirect']) && is_string($_POST['redirect'])) {
            $candidate = $_POST['redirect'];
            // 防开放重定向：仅允许站内安全字符组成的相对路径
            if (preg_match('#^[A-Za-z0-9/_.?=&%-]+$#', $candidate)
                && strpos($candidate, '//') === false
                && strpos($candidate, Router::base() . '/') === 0) {
                $back = $candidate;
            }
        }

        $post = DB::query('posts')->where('id', '=', $postId)->where('status', '=', 'published')->first();
        if (!$post) {
            flash_set('error', '文章不存在');
            redirect($back);
        }
        if ($content === '') {
            flash_set('error', '评论内容不能为空');
            redirect($back);
        }
        if ($parentId > 0) {
            $parent = DB::query('comments')->where('id', '=', $parentId)->where('post_id', '=', $postId)->first();
            if (!$parent) {
                $parentId = 0;
            } elseif ((int) $parent['parent_id'] > 0) {
                // 仅支持一层回复
                $parentId = (int) $parent['parent_id'];
            }
        }

        $data = array(
            'post_id'   => $postId,
            'user_id'   => Auth::id(),
            'parent_id' => $parentId,
            'content'   => $content,
        );
        // 插件可拦截/改写评论内容
        $data = apply_filters('comment_before_save', $data);
        if (!is_array($data) || empty($data['content'])) {
            flash_set('error', '评论被拦截');
            redirect($back);
        }
        $data['status'] = Option::get('comment_audit', '0') === '1' ? 'pending' : 'published';
        $data['created_at'] = now();
        $commentId = DB::insert('comments', $data);
        blog_log('comment', 'comment.create', 'success', array(
            'comment_id' => $commentId, 'post_id' => $postId, 'status' => $data['status'],
        ));
        flash_set('success', $data['status'] === 'pending' ? '评论已提交，等待审核' : '评论发表成功');
        redirect($back);
    }

    /** 评论所属文章的回跳 URL（可带锚点定位回原评论） */
    private static function commentBackUrl(array $comment, $anchor = '')
    {
        $post = DB::query('posts')->where('id', '=', (int) $comment['post_id'])
            ->first(array('id', 'slug'));
        if (!$post) {
            return Router::url('home');
        }
        return Router::url('post', array('slug' => (string) $post['slug'], 'id' => (int) $post['id'])) . $anchor;
    }

    /** 修改自己的评论（POST + CSRF + 登录 + edit_own_comments） */
    private static function commentUpdate()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect(Router::url('home'));
        }
        Csrf::verifyOrDie();
        if (!Auth::check()) {
            flash_set('error', '请先登录后再操作评论');
            redirect(Router::url('login'));
        }
        Auth::require_cap('edit_own_comments');

        $commentId = input_int('comment_id', 0, 'post');
        $content = input_text('content', '', 2000, 'post');
        $comment = DB::query('comments')->where('id', '=', $commentId)->first();
        if (!$comment || $comment['status'] === 'trash') {
            flash_set('error', '评论不存在');
            redirect(Router::url('home'));
        }
        $back = self::commentBackUrl($comment, '#comment-' . (int) $comment['id']);
        // 仅允许修改本人评论（管理员管理全部评论走后台 comment 模块）
        if ((int) $comment['user_id'] !== Auth::id()) {
            blog_log('comment', 'comment.update', 'fail', array('comment_id' => $commentId, 'reason' => 'not_owner'));
            flash_set('error', '只能修改自己发表的评论');
            redirect($back);
        }
        if ($content === '') {
            flash_set('error', '评论内容不能为空');
            redirect($back);
        }
        $data = array(
            'post_id'   => (int) $comment['post_id'],
            'user_id'   => (int) $comment['user_id'],
            'parent_id' => (int) $comment['parent_id'],
            'content'   => $content,
        );
        // 与新建评论同一过滤链，插件可拦截/改写
        $data = apply_filters('comment_before_save', $data);
        if (!is_array($data) || empty($data['content'])) {
            flash_set('error', '评论被拦截');
            redirect($back);
        }
        // 评论审核开关开启时，修改后的内容需重新过审，防止绕过审核
        $status = Option::get('comment_audit', '0') === '1' ? 'pending' : $comment['status'];
        DB::update('comments', array(
            'content' => $data['content'],
            'status'  => $status,
        ), array('id' => $commentId));
        blog_log('comment', 'comment.update', 'success', array('comment_id' => $commentId, 'status' => $status));
        flash_set('success', $status === 'pending' ? '评论已修改，待审核后显示' : '评论已修改');
        redirect($back);
    }

    /** 删除自己的评论（软删入回收站；存在回复时禁止删除） */
    private static function commentDelete()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect(Router::url('home'));
        }
        Csrf::verifyOrDie();
        if (!Auth::check()) {
            flash_set('error', '请先登录后再操作评论');
            redirect(Router::url('login'));
        }
        Auth::require_cap('delete_own_comments');

        $commentId = input_int('comment_id', 0, 'post');
        $comment = DB::query('comments')->where('id', '=', $commentId)->first();
        if (!$comment || $comment['status'] === 'trash') {
            flash_set('error', '评论不存在');
            redirect(Router::url('home'));
        }
        $back = self::commentBackUrl($comment);
        if ((int) $comment['user_id'] !== Auth::id()) {
            blog_log('comment', 'comment.delete', 'fail', array('comment_id' => $commentId, 'reason' => 'not_owner'));
            flash_set('error', '只能删除自己发表的评论');
            redirect($back);
        }
        // 有未删回复时禁止删除，避免回复成为无法展示的孤儿数据
        $childCount = DB::query('comments')
            ->where('parent_id', '=', $commentId)
            ->where('status', '!=', 'trash')
            ->count();
        if ($childCount > 0) {
            flash_set('error', '该评论存在回复，无法删除');
            redirect($back);
        }
        DB::update('comments', array('status' => 'trash'), array('id' => $commentId));
        blog_log('comment', 'comment.delete', 'success', array('comment_id' => $commentId));
        flash_set('success', '评论已删除');
        redirect($back);
    }

    /** 发送验证码（AJAX） */
    private static function verifySend()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_out(array('code' => 405, 'msg' => 'Method Not Allowed'));
        }
        Csrf::verifyOrDie();
        $scene = input_enum('scene', array('register', 'reset', 'profile'), '', 'post');
        $channel = input_enum('channel', array('email', 'sms'), '', 'post');

        if ($scene === '') {
            json_out(array('code' => 1, 'msg' => '参数不完整'));
        }
        // 个人资料改绑场景仅限登录用户，防止被当作对外发码接口滥用
        if ($scene === 'profile' && !Auth::check()) {
            json_out(array('code' => 1, 'msg' => '请先登录'));
        }

        if ($scene === 'reset') {
            // 找回场景：target 为用户名或邮箱，由服务端解析为实际联系方式与渠道，
            // 不能先按邮箱/手机格式预校验（用户名必然不通过，会误报“参数不完整”）
            $account = input_text('target', '', 128, 'post');
            if ($account === '') {
                json_out(array('code' => 1, 'msg' => '参数不完整'));
            }
            $target = '';
            if (strpos($account, '@') !== false) {
                $user = DB::query('users')->where('email', '=', $account)->first();
                $channel = 'email';
                $target = $user ? (string) $user['email'] : '';
            } else {
                $user = DB::query('users')->where('username', '=', $account)->first();
                if ($user) {
                    if (!empty($user['phone']) && Plugin::isActive('aliyun-sms')) {
                        $channel = 'sms';
                        $target = $user['phone'];
                    } else {
                        $channel = 'email';
                        $target = (string) $user['email'];
                    }
                }
            }
            // 不暴露账号是否存在：统一提示已发送（若真实存在才会收到验证码）
            if ($target === '') {
                json_out(array('code' => 0, 'msg' => '验证码已发送'));
            }
        } else {
            $target = $channel === 'email'
                ? input_email('target', '', 'post')
                : input_phone('target', '', 'post');
            if ($channel === '' || $target === '') {
                json_out(array('code' => 1, 'msg' => '参数不完整'));
            }
        }

        if ($channel === 'email' && !Plugin::isActive('smtp-mailer')) {
            json_out(array('code' => 1, 'msg' => '邮件发送未启用'));
        }
        if ($channel === 'sms' && !Plugin::isActive('aliyun-sms')) {
            json_out(array('code' => 1, 'msg' => '短信发送未启用'));
        }

        // 频率限制：60s 重发间隔
        $recent = DB::query('verify_codes')
            ->where('scene', '=', $scene)
            ->where('target', '=', $target)
            ->where('created_at', '>', date('Y-m-d H:i:s', time() - 60))
            ->count();
        if ($recent > 0) {
            json_out(array('code' => 1, 'msg' => '发送过于频繁，请 60 秒后重试'));
        }
        // 每日上限：同目标每日 ≤10 条
        $today = DB::query('verify_codes')
            ->where('target', '=', $target)
            ->where('created_at', '>', date('Y-m-d 00:00:00'))
            ->count();
        if ($today >= 10) {
            json_out(array('code' => 1, 'msg' => '今日发送次数已达上限'));
        }

        $code = (string) random_int(100000, 999999);
        $handled = apply_filters('send_verify_code', false, $scene, $target, $channel, $code);
        if ($handled === true) {
            json_out(array('code' => 0, 'msg' => '验证码已发送'));
        }
        json_out(array('code' => 1, 'msg' => '发送渠道不可用'));
    }

    /** 验证码核验（AJAX，供前端即时反馈；提交时服务端会再次核验） */
    private static function verifyCheck()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_out(array('code' => 405, 'msg' => 'Method Not Allowed'));
        }
        Csrf::verifyOrDie();
        $scene = input_enum('scene', array('register', 'reset', 'profile'), '', 'post');
        $channel = input_enum('channel', array('email', 'sms'), '', 'post');
        $target = $channel === 'email'
            ? input_email('target', '', 'post')
            : input_phone('target', '', 'post');
        $code = input_text('code', '', 6, 'post');
        if ($scene === '' || $target === '' || $code === '') {
            json_out(array('code' => 1, 'msg' => '参数不完整'));
        }
        if ($scene === 'profile' && !Auth::check()) {
            json_out(array('code' => 1, 'msg' => '请先登录'));
        }
        $ok = self::checkVerifyCode($scene, $target, $code, $channel);
        json_out($ok ? array('code' => 0, 'msg' => '验证通过') : array('code' => 1, 'msg' => '验证码错误'));
    }

    /**
     * 验证码核验统一实现：优先走插件（verify_code_check 过滤器），否则本地表核验
     * 错误尝试 5 次作废
     */
    public static function checkVerifyCode($scene, $target, $code, $channel)
    {
        $pluginResult = apply_filters('verify_code_check', null, $scene, $target, $code, $channel);
        if ($pluginResult !== null) {
            return (bool) $pluginResult;
        }
        return self::localVerifyCode($scene, $target, $code, $channel, false);
    }

    /** 本地 verify_codes 表核验 */
    private static function localVerifyCode($scene, $target, $code, $channel, $markUsed)
    {
        $row = DB::query('verify_codes')
            ->where('scene', '=', $scene)
            ->where('target', '=', $target)
            ->where('channel', '=', $channel)
            ->where('used', '=', 0)
            ->orderBy('id', 'DESC')
            ->first();
        if (!$row) {
            return false;
        }
        if (strtotime($row['expires_at']) < time()) {
            return false;
        }
        if ((int) $row['attempts'] >= 5) {
            return false;
        }
        if (!hash_equals((string) $row['code'], (string) $code)) {
            DB::query('verify_codes')->where('id', '=', (int) $row['id'])
                ->update(array('attempts' => (int) $row['attempts'] + 1));
            return false;
        }
        if ($markUsed) {
            DB::query('verify_codes')->where('id', '=', (int) $row['id'])
                ->update(array('used' => 1));
        }
        return true;
    }

    /** 标记验证码已使用（后台改绑邮箱/手机场景亦需复用，故公开） */
    public static function markCodeUsed($scene, $target, $channel)
    {
        $row = DB::query('verify_codes')
            ->where('scene', '=', $scene)
            ->where('target', '=', $target)
            ->where('channel', '=', $channel)
            ->where('used', '=', 0)
            ->orderBy('id', 'DESC')
            ->first();
        if ($row) {
            DB::query('verify_codes')->where('id', '=', (int) $row['id'])
                ->update(array('used' => 1));
        }
    }

    /** 统一 404 页 */
    public static function notFound()
    {
        http_response_code(404);
        Theme::render('404', array(
            'page_type' => '404',
            'title'     => '页面不存在',
        ));
    }
}
