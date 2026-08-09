<?php
/**
 * 统一审计日志组件（等保二级·安全审计）
 * 日志只增不改：不提供 UPDATE/DELETE 接口，仅允许留存期到期自动清理与导出
 */
defined('APP_BOOT') or exit;

class Logger
{
    /**
     * 写入一条审计记录（自动附带操作者/角色快照/IP/UA）
     *
     * @param string $category 事件类别 auth/post/comment/user/setting/template/plugin/verify/security
     * @param string $action   具体动作，如 login、login.fail
     * @param string $result   success/fail
     * @param array  $detail   详情（入库前脱敏，禁止含明文密码/验证码/密钥）
     * @param int|null    $userId 操作者 ID，null 表示当前登录者
     * @param string|null $role   角色快照，null 表示当前角色
     * @return void
     */
    public static function write($category, $action, $result, array $detail = array(), $userId = null, $role = null)
    {
        try {
            $detail = self::sanitize($detail);
            DB::insert('logs', array(
                'user_id'    => $userId !== null ? (int) $userId : Auth::id(),
                'role'       => $role !== null ? $role : (Auth::check() ? Auth::role() : 'guest'),
                'category'   => mb_substr($category, 0, 24),
                'action'     => mb_substr($action, 0, 64),
                'result'     => $result === 'success' ? 'success' : 'fail',
                'detail'     => json_encode($detail, JSON_UNESCAPED_UNICODE),
                'ip'         => client_ip(),
                'ua'         => client_ua(),
                'created_at' => now(),
            ));
        } catch (Exception $ex) {
            // 日志写入失败不能阻断业务，仅记入服务器错误日志
            error_log('blog_log error: ' . $ex->getMessage());
        }
    }

    /**
     * 详情脱敏：剔除敏感键、邮箱/手机号打码
     *
     * @param array $detail 原始详情
     * @return array
     */
    private static function sanitize(array $detail)
    {
        $drop = array('password', 'old_password', 'new_password', 'password_confirm',
            'code', 'verify_code', 'access_key_secret', 'accesskeysecret',
            'smtp_pass', 'smtp_password', 'secret');
        foreach ($detail as $key => $value) {
            $lower = strtolower($key);
            if (in_array($lower, $drop, true)) {
                $detail[$key] = '***';
                continue;
            }
            if (is_string($value)) {
                if (preg_match('/^1[3-9]\d{9}$/', $value)) {
                    $detail[$key] = mask_phone($value);
                } elseif (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $detail[$key] = mask_email($value);
                }
            }
        }
        return $detail;
    }

    /**
     * 留存期到期清理（惰性执行，每日最多一次；下限 180 天）
     *
     * @return void
     */
    public static function purgeExpired()
    {
        $last = (int) Option::get('log_last_purge', '0');
        if (time() - $last < 86400) {
            return;
        }
        Option::set('log_last_purge', (string) time());
        $days = max(180, (int) Option::get('log_retention_days', 180));
        $cutoff = date('Y-m-d H:i:s', time() - $days * 86400);
        DB::purgeLogsBefore($cutoff);
    }
}

/**
 * 业务审计日志入口
 *
 * @param string $category 事件类别
 * @param string $action   动作
 * @param string $result   success/fail
 * @param array  $detail   详情
 * @return void
 */
function blog_log($category, $action, $result, $detail = array())
{
    Logger::write($category, $action, $result, (array) $detail);
}

/**
 * 插件日志入口：约定 action 以插件 slug 开头（如 smtp.send）
 *
 * @param string $action 动作（建议 {slug}.{动作}）
 * @param mixed  $detail 详情
 * @return void
 */
function plugin_log($action, $detail = array())
{
    Logger::write('plugin', $action, isset($detail['result']) ? $detail['result'] : 'success', (array) $detail);
}
