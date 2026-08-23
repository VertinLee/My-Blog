<?php
/**
 * 后台视图：仪表盘
 * 时段问候所有登录角色可见；全站统计与审计日志预览仅管理员可见（控制器层同样不向非管理员提供数据）
 */
defined('APP_BOOT') or exit;
// 时段问候：5-11 早上、11-14 中午、其余时间（含凌晨）晚上
$greetHour = (int) date('G');
if ($greetHour >= 5 && $greetHour < 11) {
    $greetKey = 'admin.dashboard.greeting_morning';
} elseif ($greetHour >= 11 && $greetHour < 14) {
    $greetKey = 'admin.dashboard.greeting_noon';
} else {
    $greetKey = 'admin.dashboard.greeting_night';
}
$greetUser = Auth::user();
?>
<div class="card dashboard-greeting">
    <h3><?php echo e(admin_t($greetKey, array($greetUser['nickname']))); ?></h3>
    <p class="greet-welcome"><?php echo e(admin_t('admin.dashboard.welcome', array(site_name()))); ?></p>
</div>

<?php if (Auth::isAdmin()): ?>
<?php // 统计卡片：layui 栅格，窄屏两列、宽屏六列 ?>
<div class="layui-row layui-col-space14">
    <?php $statItems = array(
        array('num' => (int) $stats['posts'], 'lab' => admin_t('admin.dashboard.stat_posts')),
        array('num' => (int) $stats['published'], 'lab' => admin_t('admin.dashboard.stat_published')),
        array('num' => (int) $stats['pendingPosts'], 'lab' => admin_t('admin.dashboard.stat_pending_posts')),
        array('num' => (int) $stats['comments'], 'lab' => admin_t('admin.dashboard.stat_comments')),
        array('num' => (int) $stats['pendingComments'], 'lab' => admin_t('admin.dashboard.stat_pending_comments')),
        array('num' => (int) $stats['users'], 'lab' => admin_t('admin.dashboard.stat_users')),
    ); ?>
    <?php foreach ($statItems as $st): ?>
    <div class="layui-col-xs6 layui-col-sm4 layui-col-md2">
        <div class="stat-item">
            <div class="num"><?php echo $st['num']; ?></div>
            <div class="lab"><?php echo e($st['lab']); ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (Auth::check_cap('view_logs')): ?>
<div class="card" style="margin-top:18px">
    <h3><?php echo e(admin_t('admin.dashboard.recent_logs')); ?></h3>
    <div class="table-wrap">
    <table class="layui-table" lay-skin="line">
        <thead>
            <tr><th><?php echo e(admin_t('admin.log.col_time')); ?></th><th><?php echo e(admin_t('admin.log.col_user')); ?></th><th><?php echo e(admin_t('admin.log.col_category')); ?></th><th><?php echo e(admin_t('admin.log.col_action')); ?></th><th><?php echo e(admin_t('admin.log.col_result')); ?></th><th>IP</th></tr>
        </thead>
        <tbody>
        <?php foreach ($recentLogs as $logItem): ?>
        <tr>
            <td><?php echo e($logItem['created_at']); ?></td>
            <td><?php echo (int) $logItem['user_id']; ?>（<?php echo e($logItem['role']); ?>）</td>
            <td><?php echo e($logItem['category']); ?></td>
            <td><?php echo e($logItem['action']); ?></td>
            <td><?php if ($logItem['result'] === 'success'): ?><span class="layui-badge layui-bg-green"><?php echo e(admin_t('admin.log.success')); ?></span><?php else: ?><span class="layui-badge layui-bg-red"><?php echo e(admin_t('admin.log.fail')); ?></span><?php endif; ?></td>
            <td><?php echo e($logItem['ip']); ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <p><a href="<?php echo e(site_base_admin('log/list')); ?>"><?php echo e(admin_t('admin.dashboard.goto_logs')); ?> →</a></p>
</div>
<?php endif; ?>
