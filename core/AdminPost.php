<?php
/**
 * 后台文章管理：列表/编辑/回收站/审核（文章审核开关开启时仅管理员可发布）
 */
defined('APP_BOOT') or exit;

class AdminPost
{
    /** 文章列表（含状态/类型筛选与分页；独立页面与文章同表，需可在后台管理） */
    public static function listAction()
    {
        Auth::require_cap('edit_posts');
        $status = input_enum('status', array('all', 'published', 'pending', 'draft', 'trash'), 'all', 'get');
        $type = input_enum('type', array('all', 'post', 'page'), 'all', 'get');
        $page = max(1, input_int('page', 1, 'get'));
        $perPage = 15;

        $query = DB::query('posts');
        if ($type === 'post') {
            $query->where('is_page', '=', 0);
        } elseif ($type === 'page') {
            $query->where('is_page', '=', 1);
        }
        if ($status !== 'all') {
            $query->where('status', '=', $status);
        }
        $total = $query->count();
        $totalPages = max(1, (int) ceil($total / $perPage));
        // 越界页码钳制到末页，避免空表格
        $page = min($page, $totalPages);

        $q = DB::query('posts');
        if ($type === 'post') {
            $q->where('is_page', '=', 0);
        } elseif ($type === 'page') {
            $q->where('is_page', '=', 1);
        }
        if ($status !== 'all') {
            $q->where('status', '=', $status);
        }
        $posts = $q->orderBy('is_top', 'DESC')->orderBy('id', 'DESC')->limit($perPage, ($page - 1) * $perPage)->select();

        // 作者与分类映射
        $users = array();
        $rows = DB::query('users')->select(array('id', 'nickname'));
        foreach ($rows as $r) {
            $users[(int) $r['id']] = $r['nickname'];
        }
        $cats = array();
        $rows = DB::query('categories')->select(array('id', 'name'));
        foreach ($rows as $r) {
            $cats[(int) $r['id']] = $r['name'];
        }

        Admin::render('文章管理', 'post_list', array(
            'posts' => $posts, 'status' => $status, 'type' => $type, 'page' => $page,
            'totalPages' => $totalPages, 'users' => $users, 'cats' => $cats,
        ));
    }

    /** 切换置顶（属站点级编排动作，限 moderate_posts 权限；仅已发布文章可置顶） */
    public static function topAction()
    {
        Auth::require_cap('moderate_posts');
        $id = input_int('id', 0, 'post');
        // 返回列表页时保持当前状态/类型筛选
        $status = input_enum('status', array('all', 'published', 'pending', 'draft', 'trash'), 'all', 'post');
        $type = input_enum('type', array('all', 'post', 'page'), 'all', 'post');
        $back = site_base_admin('post/list'
            . ($status !== 'all' ? '&status=' . $status : '')
            . ($type !== 'all' ? '&type=' . $type : ''));

        $post = DB::query('posts')->where('id', '=', $id)->where('is_page', '=', 0)->first();
        if (!$post || $post['status'] !== 'published') {
            flash_set('error', '仅已发布文章可置顶');
            redirect($back);
        }
        $new = empty($post['is_top']) ? 1 : 0;
        DB::update('posts', array('is_top' => $new), array('id' => (int) $id));
        blog_log('content', 'post.top', 'success', array('post_id' => (int) $id, 'is_top' => $new));
        flash_set('success', $new === 1 ? '已置顶，前台列表将优先展示' : '已取消置顶');
        redirect($back);
    }

    /** 文章编辑页（新建/修改） */
    public static function editAction()
    {
        Auth::require_cap('edit_posts');
        $id = input_int('id', 0, 'get');
        $post = null;
        if ($id > 0) {
            $post = DB::query('posts')->where('id', '=', $id)->first();
            if (!$post) {
                flash_set('error', '文章不存在');
                redirect(site_base_admin('post/list'));
            }
            // 编辑他人文章需要 edit_others_posts 能力
            if ((int) $post['author_id'] !== Auth::id() && !Auth::check_cap('edit_others_posts')) {
                Admin::forbidden();
            }
        }
        $categories = DB::query('categories')->orderBy('sort', 'ASC')->select();
        // 当前页面是否已勾选“显示在侧边栏导航”（存 options 的 nav_page_ids）
        $navIds = array_map('intval', Option::getJson('nav_page_ids', array()));
        Admin::render($id > 0 ? '编辑文章' : '新增文章', 'post_edit', array(
            'post' => $post, 'categories' => $categories,
            'postAudit' => Option::get('post_audit', '0') === '1',
            'inNav' => $post && in_array((int) $post['id'], $navIds, true),
        ));
    }

    /** 保存文章（新建/更新） */
    public static function saveAction()
    {
        Auth::require_cap('edit_posts');
        $id = input_int('id', 0, 'post');
        $title = input_text('title', '', 255, 'post');
        $slug = input_slug('slug', '', 'post');
        $categoryId = input_int('category_id', 0, 'post');
        $content = input_longtext('content', '');
        $excerpt = input_text('excerpt', '', 500, 'post');
        $cover = input_text('cover', '', 255, 'post');
        $status = input_enum('status', array('draft', 'pending', 'published'), 'draft', 'post');
        $isPage = input_int('is_page', 0, 'post') === 1 ? 1 : 0;

        if ($title === '') {
            flash_set('error', '标题不能为空');
            redirect(site_base_admin('post/edit' . ($id > 0 ? '&id=' . $id : '')));
        }

        // 封面与头像同一口径：仅允许站内 uploads/ 下相对路径，拒绝 .. 防路径穿越与站外引用
        if ($cover !== ''
            && (strpos($cover, '..') !== false || !preg_match('#^uploads/[A-Za-z0-9/._-]+$#', $cover))
        ) {
            flash_set('error', '封面路径非法（仅允许 uploads/ 目录下的图片）');
            redirect(site_base_admin('post/edit' . ($id > 0 ? '&id=' . $id : '')));
        }

        // slug 不得为纯数字：文章路由 /post/{key}.html 对纯数字 key 按 id 解析，
        // 纯数字 slug 会永远被 id 查找拦截而无法访问；页面路由同库同规则，一并限制
        if ($slug !== '' && ctype_digit($slug)) {
            flash_set('error', '别名（slug）不能为纯数字，避免与文章 ID 访问冲突');
            redirect(site_base_admin('post/edit' . ($id > 0 ? '&id=' . $id : '')));
        }

        // slug 唯一性校验
        if ($slug !== '') {
            $q = DB::query('posts')->where('slug', '=', $slug);
            if ($id > 0) {
                $q->where('id', '!=', $id);
            }
            if ($q->value('id')) {
                flash_set('error', '别名（slug）已被占用');
                redirect(site_base_admin('post/edit' . ($id > 0 ? '&id=' . $id : '')));
            }
        }

        // 文章审核开关：开启时非管理员提交发布一律转 pending
        $postAudit = Option::get('post_audit', '0') === '1';
        if ($status === 'published' && $postAudit && !Auth::isAdmin()) {
            $status = 'pending';
        }
        // 非管理员不允许直接发布（审核开启时）
        if ($status === 'published' && $postAudit && !Auth::check_cap('moderate_posts')) {
            $status = 'pending';
        }

        $data = array(
            'title'       => $title,
            // 空别名存 NULL：唯一索引只允许 NULL 重复（空串重复会撞约束）
            'slug'        => $slug !== '' ? $slug : null,
            'content'     => $content,
            'excerpt'     => $excerpt,
            'category_id' => $categoryId,
            'cover'       => $cover,
            'status'      => $status,
            'is_page'     => $isPage,
            'updated_at'  => now(),
        );

        if ($id > 0) {
            $post = DB::query('posts')->where('id', '=', $id)->first();
            if (!$post) {
                flash_set('error', '文章不存在');
                redirect(site_base_admin('post/list'));
            }
            if ((int) $post['author_id'] !== Auth::id() && !Auth::check_cap('edit_others_posts')) {
                Admin::forbidden();
            }
            DB::update('posts', $data, array('id' => $id));
            blog_log('post', 'post.update', 'success', array('post_id' => $id, 'title' => $title, 'status' => $status));
            $isNew = false;
        } else {
            $data['author_id'] = Auth::id();
            $data['views'] = 0;
            $data['created_at'] = now();
            $id = DB::insert('posts', $data);
            blog_log('post', 'post.create', 'success', array('post_id' => $id, 'title' => $title, 'status' => $status));
            $isNew = true;
        }
        // 保存完成钩子：插件在此持久化随文章的扩展数据（自有表单字段自行经输入校验器读取）
        do_action('post_saved', $id, $data, $isNew);

        // 侧边栏导航展示仅对独立页面生效：非独立页面即使传入该选项也一律忽略（下方 array_diff 同时会移除
        // 其在导航中的既有记录，防止页面转回普通文章后残留）；新建页面默认不加入
        $showInNav = $isPage === 1 && input_int('show_in_nav', 0, 'post') === 1;
        $navIds = array();
        foreach (Option::getJson('nav_page_ids', array()) as $navId) {
            $navIds[] = (int) $navId;
        }
        $navIds = array_values(array_diff($navIds, array((int) $id)));
        if ($showInNav) {
            $navIds[] = (int) $id;
        }
        Option::set('nav_page_ids', array_values(array_unique($navIds)));

        flash_set('success', '保存成功');
        redirect(site_base_admin('post/list'));
    }

    /** 移入回收站（仅管理员可删除） */
    public static function deleteAction()
    {
        Auth::require_cap('delete_posts');
        $id = input_int('id', 0, 'post');
        if ($id > 0) {
            DB::update('posts', array('status' => 'trash'), array('id' => $id));
            blog_log('post', 'post.delete', 'success', array('post_id' => $id));
            flash_set('success', '已移入回收站');
        }
        redirect(site_base_admin('post/list&status=trash'));
    }

    /** 从回收站恢复为草稿 */
    public static function restoreAction()
    {
        Auth::require_cap('delete_posts');
        $id = input_int('id', 0, 'post');
        if ($id > 0) {
            DB::update('posts', array('status' => 'draft'), array('id' => $id, 'status' => 'trash'));
            blog_log('post', 'post.restore', 'success', array('post_id' => $id));
            flash_set('success', '已恢复为草稿');
        }
        redirect(site_base_admin('post/list&status=trash'));
    }

    /** 彻底删除（仅回收站内，仅管理员） */
    public static function destroyAction()
    {
        Auth::require_cap('delete_posts');
        $id = input_int('id', 0, 'post');
        if ($id > 0) {
            $post = DB::query('posts')->where('id', '=', $id)->where('status', '=', 'trash')->first();
            if ($post) {
                DB::delete('posts', array('id' => $id));
                DB::delete('comments', array('post_id' => $id));
                // 文章已不存在，插件可清理随文章的扩展数据
                do_action('post_deleted', $id);
                blog_log('post', 'post.destroy', 'success', array('post_id' => $id));
                flash_set('success', '已彻底删除');
            }
        }
        redirect(site_base_admin('post/list&status=trash'));
    }

    /** 文章审核（仅管理员：通过/驳回） */
    public static function auditAction()
    {
        Auth::require_cap('moderate_posts');
        $id = input_int('id', 0, 'post');
        $decision = input_enum('decision', array('approve', 'reject'), '', 'post');
        $post = DB::query('posts')->where('id', '=', $id)->where('status', '=', 'pending')->first();
        if ($post && $decision !== '') {
            $newStatus = $decision === 'approve' ? 'published' : 'draft';
            DB::update('posts', array('status' => $newStatus, 'updated_at' => now()), array('id' => $id));
            blog_log('post', 'post.audit', 'success', array('post_id' => $id, 'decision' => $decision));
            flash_set('success', $decision === 'approve' ? '已通过并发布' : '已驳回为草稿');
        }
        redirect(site_base_admin('post/list&status=pending'));
    }
}
