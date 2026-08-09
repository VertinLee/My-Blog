<?php
/**
 * 后台个人资料：昵称/邮箱/手机/头像 + 修改密码（能力点 edit_profile）
 * 敏感操作（改邮箱/手机、改密）必须重新验证当前密码
 */
defined('APP_BOOT') or exit;

class AdminProfile
{
    /** 个人资料页 */
    public static function indexAction()
    {
        Auth::require_cap('edit_profile');
        Admin::render('个人资料', 'profile', array('user' => Auth::user()));
    }

    /** 保存资料（修改邮箱/手机须验证当前密码） */
    public static function saveAction()
    {
        Auth::require_cap('edit_profile');
        $user = Auth::user();
        $nickname = input_text('nickname', '', 64, 'post');
        $email = input_email('email', '', 'post');
        $phone = input_phone('phone', '', 'post');
        $avatar = input_text('avatar', '', 255, 'post');
        $password = isset($_POST['password']) ? (string) $_POST['password'] : '';

        // 头像仅允许站内 uploads/ 下的相对路径；拒绝 .. 防路径穿越
        if ($avatar !== ''
            && (strpos($avatar, '..') !== false || !preg_match('#^uploads/[A-Za-z0-9/._-]+$#', $avatar))
        ) {
            flash_set('error', '头像路径非法');
            redirect(site_base_admin('profile'));
        }

        $oldEmail = $user['email'] !== null ? $user['email'] : '';
        $contactChanged = $email !== $oldEmail || $phone !== $user['phone'];
        if ($contactChanged) {
            // 敏感操作重验当前密码
            if ($password === '' || !password_verify($password, $user['password'])) {
                blog_log('user', 'profile.update', 'fail', array('reason' => 'password_wrong'));
                flash_set('error', '修改邮箱/手机须填写正确的当前密码');
                redirect(site_base_admin('profile'));
            }
            if ($email !== '' && $email !== $oldEmail) {
                $exists = DB::query('users')->where('email', '=', $email)->where('id', '!=', (int) $user['id'])->value('id');
                if ($exists) {
                    flash_set('error', '邮箱已被占用');
                    redirect(site_base_admin('profile'));
                }
                // 邮件插件启用时，改绑邮箱必须通过新邮箱的验证码核验；未启用则免验
                if (Plugin::isActive('smtp-mailer')) {
                    $emailCode = input_text('email_code', '', 6, 'post');
                    if (!Front::checkVerifyCode('profile', $email, $emailCode, 'email')) {
                        blog_log('user', 'profile.update', 'fail', array('reason' => 'email_code_wrong'));
                        flash_set('error', '邮箱验证码错误或已过期');
                        redirect(site_base_admin('profile'));
                    }
                }
            }
            if ($phone !== '' && $phone !== $user['phone']) {
                // 手机号作为身份核验凭据必须全局唯一
                $exists = DB::query('users')->where('phone', '=', $phone)->where('id', '!=', (int) $user['id'])->value('id');
                if ($exists) {
                    flash_set('error', '该手机号已被其他账号使用');
                    redirect(site_base_admin('profile'));
                }
                // 短信插件启用时，改绑手机必须通过新手机的验证码核验；未启用则免验
                if (Plugin::isActive('aliyun-sms')) {
                    $smsCode = input_text('sms_code', '', 6, 'post');
                    if (!Front::checkVerifyCode('profile', $phone, $smsCode, 'sms')) {
                        blog_log('user', 'profile.update', 'fail', array('reason' => 'sms_code_wrong'));
                        flash_set('error', '短信验证码错误或已过期');
                        redirect(site_base_admin('profile'));
                    }
                }
            }
        }

        DB::update('users', array(
            'nickname' => $nickname !== '' ? $nickname : $user['nickname'],
            'email'    => $email !== '' ? $email : null,
            'phone'    => $phone,
            'avatar'   => $avatar,
        ), array('id' => (int) $user['id']));
        // 核验通过的验证码标记已用，防止重放
        if ($contactChanged) {
            if ($email !== '' && $email !== $oldEmail && Plugin::isActive('smtp-mailer')) {
                Front::markCodeUsed('profile', $email, 'email');
            }
            if ($phone !== '' && $phone !== $user['phone'] && Plugin::isActive('aliyun-sms')) {
                Front::markCodeUsed('profile', $phone, 'sms');
            }
        }
        blog_log('user', 'profile.update', 'success', array(
            'contact_changed' => $contactChanged ? 1 : 0,
        ));
        flash_set('success', '资料已保存');
        redirect(site_base_admin('profile'));
    }

    /** 修改密码页（密码过期强制改密亦导向此处） */
    public static function passwordAction()
    {
        Auth::require_cap('edit_profile');
        Admin::render('修改密码', 'profile_password', array(
            'expired' => !empty($_SESSION['pwd_expired']),
        ));
    }

    /** 执行改密（验证原密码，走统一策略校验） */
    public static function passwordSaveAction()
    {
        Auth::require_cap('edit_profile');
        $oldPassword = isset($_POST['old_password']) ? (string) $_POST['old_password'] : '';
        $newPassword = isset($_POST['new_password']) ? (string) $_POST['new_password'] : '';
        $confirm = isset($_POST['confirm_password']) ? (string) $_POST['confirm_password'] : '';
        $wasExpired = !empty($_SESSION['pwd_expired']);

        if ($newPassword !== $confirm) {
            flash_set('error', '两次输入的新密码不一致');
            redirect(site_base_admin('profile/password'));
        }
        $result = Auth::changePassword(Auth::id(), $oldPassword, $newPassword);
        if (!$result['ok']) {
            flash_set('error', $result['msg']);
            redirect(site_base_admin('profile/password'));
        }
        if ($wasExpired) {
            // 强制改密场景单独留痕（changePassword 内部已清除 pwd_expired 标记）
            blog_log('auth', 'password.expired_change', 'success', array('user_id' => Auth::id()));
        }
        flash_set('success', '密码修改成功');
        redirect(site_base_admin('profile'));
    }
}
