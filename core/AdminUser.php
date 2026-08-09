<?php
/**
 * 后台用户管理：列表/建号/编辑/角色授予/解锁/禁用/封禁/注销（仅管理员，能力点 manage_users）
 * 红线：editor/admin 角色只能由管理员在此授予；公开注册只产生 user
 */
defined('APP_BOOT') or exit;

class AdminUser
{
    /** 用户列表 */
    public static function listAction()
    {
        Auth::require_cap('manage_users');
        $page = max(1, input_int('page', 1, 'get'));
        $perPage = 15;
        $total = DB::query('users')->count();
        $totalPages = max(1, (int) ceil($total / $perPage));
        // 越界页码钳制到末页，避免空表格
        $page = min($page, $totalPages);
        $users = DB::query('users')
            ->orderBy('id', 'ASC')
            ->limit($perPage, ($page - 1) * $perPage)
            ->select();
        Admin::render('用户管理', 'user_list', array(
            'users' => $users, 'page' => $page, 'totalPages' => $totalPages,
            // 安装管理员受保护不可封禁/注销，视图需据此隐藏操作按钮
            'rootId' => self::rootAdminId(),
        ));
    }

    /** 用户编辑页（新建/修改） */
    public static function editAction()
    {
        Auth::require_cap('manage_users');
        $id = input_int('id', 0, 'get');
        $user = null;
        if ($id > 0) {
            $user = DB::query('users')->where('id', '=', $id)->first();
            if (!$user) {
                flash_set('error', '用户不存在');
                redirect(site_base_admin('user/list'));
            }
        }
        Admin::render($id > 0 ? '编辑用户' : '新增用户', 'user_edit', array(
            'user' => $user,
            // 安装管理员（安装程序创建的首位管理员）角色与状态不可变更
            'isRoot' => $user !== null && (int) $user['id'] === self::rootAdminId(),
        ));
    }

    /** 保存用户（新建/更新） */
    public static function saveAction()
    {
        Auth::require_cap('manage_users');
        $id = input_int('id', 0, 'post');
        $nickname = input_text('nickname', '', 64, 'post');
        $email = input_email('email', '', 'post');
        $phone = input_phone('phone', '', 'post');
        $role = input_enum('role', array('user', 'editor', 'admin'), 'user', 'post');
        $status = input_int('status', 1, 'post') === 1 ? 1 : 0;
        $forceChange = input_int('force_change', 0, 'post') === 1;
        $newPassword = isset($_POST['new_password']) ? (string) $_POST['new_password'] : '';

        if ($id > 0) {
            self::updateUser($id, $nickname, $email, $phone, $role, $status, $forceChange, $newPassword);
        } else {
            self::createUser($nickname, $email, $phone, $role, $forceChange);
        }
    }

    /** 新建用户：随机初始密码（一次性展示，不落日志） */
    private static function createUser($nickname, $email, $phone, $role, $forceChange)
    {
        $username = input_text('username', '', 32, 'post');
        if (!preg_match('/^[A-Za-z0-9_]{3,32}$/', $username)) {
            flash_set('error', '用户名须为 3-32 位字母、数字或下划线');
            redirect(site_base_admin('user/edit'));
        }
        if (DB::query('users')->where('username', '=', $username)->value('id')) {
            flash_set('error', '用户名已被占用');
            redirect(site_base_admin('user/edit'));
        }
        if ($email !== '' && DB::query('users')->where('email', '=', $email)->value('id')) {
            flash_set('error', '邮箱已被占用');
            redirect(site_base_admin('user/edit'));
        }
        // 手机号作为身份核验凭据必须全局唯一
        if ($phone !== '' && DB::query('users')->where('phone', '=', $phone)->value('id')) {
            flash_set('error', '该手机号已被占用');
            redirect(site_base_admin('user/edit'));
        }
        $password = self::generateStrongPassword();
        $err = Auth::validate_password_strength($password, $username);
        if ($err !== '') {
            flash_set('error', '初始密码生成异常：' . $err);
            redirect(site_base_admin('user/edit'));
        }
        $newId = DB::insert('users', array(
            'username'            => $username,
            'nickname'            => $nickname !== '' ? $nickname : $username,
            'password'            => password_hash($password, PASSWORD_DEFAULT),
            'email'               => $email !== '' ? $email : null,
            'phone'               => $phone,
            'avatar'              => '',
            'role'                => $role,
            'status'              => 1,
            // 勾选“下次登录强制改密”：将改密时间置为过期阈值之前
            'password_changed_at' => $forceChange ? '2000-01-01 00:00:00' : now(),
            'login_fail'          => 0,
            'created_at'          => now(),
        ));
        blog_log('user', 'user.create', 'success', array(
            'user_id' => $newId, 'username' => $username, 'role' => $role,
        ));
        flash_set('success', '用户已创建。初始密码（仅显示一次，请通过安全渠道告知用户）：' . $password);
        redirect(site_base_admin('user/list'));
    }

    /** 更新用户资料/角色/状态/密码 */
    private static function updateUser($id, $nickname, $email, $phone, $role, $status, $forceChange, $newPassword)
    {
        $user = DB::query('users')->where('id', '=', $id)->first();
        if (!$user) {
            flash_set('error', '用户不存在');
            redirect(site_base_admin('user/list'));
        }
        $isSelf = (int) $user['id'] === Auth::id();
        // 自我保护：不得禁用/降权自己，避免管理员把自己锁出后台
        if ($isSelf && ($status !== 1 || $role !== 'admin')) {
            flash_set('error', '不能禁用或变更自己的角色');
            redirect(site_base_admin('user/edit&id=' . $id));
        }
        // 安装管理员保护：安装程序创建的首位管理员不得被降权或禁用，防止误操作
        if ((int) $user['id'] === self::rootAdminId() && ($role !== 'admin' || $status !== 1)) {
            flash_set('error', '安装管理员账号不可降权或禁用');
            redirect(site_base_admin('user/edit&id=' . $id));
        }
        // 最后一位在职管理员不得被降权或禁用
        if ($user['role'] === 'admin' && ($role !== 'admin' || $status !== 1) && self::adminCount() <= 1) {
            flash_set('error', '系统至少需要保留一位管理员');
            redirect(site_base_admin('user/edit&id=' . $id));
        }
        if ($email !== '' && $email !== $user['email']) {
            $exists = DB::query('users')->where('email', '=', $email)->where('id', '!=', $id)->value('id');
            if ($exists) {
                flash_set('error', '邮箱已被占用');
                redirect(site_base_admin('user/edit&id=' . $id));
            }
        }
        if ($phone !== '' && $phone !== $user['phone']) {
            $exists = DB::query('users')->where('phone', '=', $phone)->where('id', '!=', $id)->value('id');
            if ($exists) {
                flash_set('error', '该手机号已被占用');
                redirect(site_base_admin('user/edit&id=' . $id));
            }
        }

        $update = array(
            'nickname' => $nickname !== '' ? $nickname : $user['nickname'],
            'email'    => $email !== '' ? $email : null,
            'phone'    => $phone,
            'role'     => $role,
            'status'   => $status,
        );
        DB::update('users', $update, array('id' => $id));
        $detail = array('target_user_id' => $id, 'role' => $role, 'status' => $status);
        blog_log('user', 'user.update', 'success', $detail);
        if ($role !== $user['role']) {
            // 角色变更必须单独留痕（等保审计要求）
            blog_log('user', 'user.role_change', 'success', array(
                'target_user_id' => $id, 'from' => $user['role'], 'to' => $role,
            ));
        }

        // 管理员重置密码（可选）
        if ($newPassword !== '') {
            $err = Auth::validate_password_strength($newPassword, $user['username']);
            if ($err !== '') {
                flash_set('error', $err);
                redirect(site_base_admin('user/edit&id=' . $id));
            }
            DB::update('users', array(
                'password'            => password_hash($newPassword, PASSWORD_DEFAULT),
                'password_changed_at' => $forceChange ? '2000-01-01 00:00:00' : now(),
            ), array('id' => $id));
            blog_log('user', 'user.password_reset', 'success', array('target_user_id' => $id));
            flash_set('success', '用户已保存，密码已重置');
        } else {
            if ($forceChange) {
                DB::update('users', array('password_changed_at' => '2000-01-01 00:00:00'), array('id' => $id));
            }
            flash_set('success', '用户已保存');
        }
        redirect(site_base_admin('user/list'));
    }

    /** 手动解除账号锁定 */
    public static function unlockAction()
    {
        Auth::require_cap('manage_users');
        $id = input_int('id', 0, 'post');
        if ($id > 0) {
            DB::update('users', array('locked_until' => null, 'login_fail' => 0), array('id' => $id));
            blog_log('user', 'user.unlock', 'success', array('target_user_id' => $id));
            flash_set('success', '已解除锁定');
        }
        redirect(site_base_admin('user/list'));
    }

    /** 封禁用户：已发布内容保留，但禁止登录（既有会话立即失效） */
    public static function banAction()
    {
        Auth::require_cap('manage_users');
        $user = self::targetUser(input_int('id', 0, 'post'));
        if (!$user) {
            flash_set('error', '用户不存在');
            redirect(site_base_admin('user/list'));
        }
        $id = (int) $user['id'];
        if ($id === Auth::id()) {
            flash_set('error', '不能封禁自己');
            redirect(site_base_admin('user/list'));
        }
        if ($id === self::rootAdminId()) {
            flash_set('error', '安装管理员账号不可封禁');
            redirect(site_base_admin('user/list'));
        }
        if ($user['role'] === 'admin' && self::adminCount() <= 1) {
            flash_set('error', '系统至少需要保留一位可用管理员');
            redirect(site_base_admin('user/list'));
        }
        DB::update('users', array('is_banned' => 1), array('id' => $id));
        blog_log('user', 'user.ban', 'success', array('target_user_id' => $id));
        flash_set('success', '已封禁该用户，其将无法登录');
        redirect(site_base_admin('user/list'));
    }

    /** 解除封禁 */
    public static function unbanAction()
    {
        Auth::require_cap('manage_users');
        $id = input_int('id', 0, 'post');
        if ($id > 0) {
            DB::update('users', array('is_banned' => 0), array('id' => $id));
            blog_log('user', 'user.unban', 'success', array('target_user_id' => $id));
            flash_set('success', '已解除封禁');
        }
        redirect(site_base_admin('user/list'));
    }

    /** 注销用户：禁止登录，前台历史内容作者匿名展示为“用户已注销”（数据不删除） */
    public static function deregisterAction()
    {
        Auth::require_cap('manage_users');
        $user = self::targetUser(input_int('id', 0, 'post'));
        if (!$user) {
            flash_set('error', '用户不存在');
            redirect(site_base_admin('user/list'));
        }
        $id = (int) $user['id'];
        if ($id === Auth::id()) {
            flash_set('error', '不能注销自己');
            redirect(site_base_admin('user/list'));
        }
        if ($id === self::rootAdminId()) {
            flash_set('error', '安装管理员账号不可注销');
            redirect(site_base_admin('user/list'));
        }
        if ($user['role'] === 'admin' && self::adminCount() <= 1) {
            flash_set('error', '系统至少需要保留一位可用管理员');
            redirect(site_base_admin('user/list'));
        }
        DB::update('users', array('is_deleted' => 1), array('id' => $id));
        blog_log('user', 'user.deregister', 'success', array('target_user_id' => $id));
        flash_set('success', '已注销该用户，其历史内容将以“用户已注销”匿名展示');
        redirect(site_base_admin('user/list'));
    }

    /** 恢复注销：撤销匿名展示并允许重新登录 */
    public static function restoreAction()
    {
        Auth::require_cap('manage_users');
        $id = input_int('id', 0, 'post');
        if ($id > 0) {
            DB::update('users', array('is_deleted' => 0), array('id' => $id));
            blog_log('user', 'user.restore', 'success', array('target_user_id' => $id));
            flash_set('success', '已恢复该用户');
        }
        redirect(site_base_admin('user/list'));
    }

    /** 按 ID 取目标用户行（不存在返回 null） */
    private static function targetUser($id)
    {
        if ((int) $id <= 0) {
            return null;
        }
        return DB::query('users')->where('id', '=', (int) $id)->first();
    }

    /** 可用管理员数量（role=admin 且未被禁用/封禁/注销，保护最后一位） */
    private static function adminCount()
    {
        return DB::query('users')
            ->where('role', '=', 'admin')
            ->where('status', '=', 1)
            ->where('is_banned', '=', 0)
            ->where('is_deleted', '=', 0)
            ->count();
    }

    /** 安装管理员：安装程序创建的首位管理员（id 最小的 admin） */
    private static function rootAdminId()
    {
        $rows = DB::query('users')
            ->where('role', '=', 'admin')
            ->orderBy('id', 'ASC')
            ->limit(1)
            ->select();
        return $rows ? (int) $rows[0]['id'] : 0;
    }

    /**
     * 生成满足口令复杂度策略的随机初始密码
     *
     * @return string
     */
    private static function generateStrongPassword()
    {
        $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lower = 'abcdefghijkmnpqrstuvwxyz';
        $digit = '23456789';
        $symbol = '!@#$%^&*';
        $all = $upper . $lower . $digit . $symbol;
        $pwd = $upper[random_int(0, strlen($upper) - 1)]
            . $lower[random_int(0, strlen($lower) - 1)]
            . $digit[random_int(0, strlen($digit) - 1)]
            . $symbol[random_int(0, strlen($symbol) - 1)];
        for ($i = 4; $i < 14; $i++) {
            $pwd .= $all[random_int(0, strlen($all) - 1)];
        }
        return str_shuffle($pwd);
    }
}
