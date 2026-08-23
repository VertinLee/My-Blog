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
     * @param array  $detail   详情（入库前脱敏，禁止含明文密码/验证码/密钥；键名 content 视为正文快照，保留原文不打码）
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
     * 详情脱敏：剔除敏感键、邮箱/手机号打码（递归处理嵌套数组）
     * 例外：键名 content 为业务正文快照（如评论审计），为满足取证一致性保留原文不打码
     *
     * @param array $detail 原始详情
     * @return array
     */
    private static function sanitize(array $detail)
    {
        // 敏感键按子串剔除（键名小写后比对），避免精确匹配漏掉 passwd/sms_code/access_token 等变体
        $sensitive = array('password', 'passwd', 'secret', 'token', 'code');
        foreach ($detail as $key => $value) {
            $lower = strtolower((string) $key);
            foreach ($sensitive as $frag) {
                if (strpos($lower, $frag) !== false) {
                    $detail[$key] = '***';
                    continue 2;
                }
            }
            if (is_array($value)) {
                $detail[$key] = self::sanitize($value);
            } elseif (is_string($value)) {
                // 正文快照键不打码，保证日志内容与落库原文逐字一致
                $detail[$key] = $lower === 'content' ? $value : self::maskString($value);
            }
        }
        return $detail;
    }

    /**
     * 字符串就地打码：手机号/邮箱出现在值的任意位置都掩码（不再要求整串匹配）
     *
     * @param string $value 原始字符串
     * @return string
     */
    private static function maskString($value)
    {
        $value = preg_replace_callback('/1[3-9]\d{9}/', function ($m) {
            return mask_phone($m[0]);
        }, $value);
        return preg_replace_callback('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', function ($m) {
            return mask_email($m[0]);
        }, $value);
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
