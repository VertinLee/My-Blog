<?php
/**
 * 后台视图：仪表盘
 * 时段问候所有登录角色可见；全站统计与审计日志预览仅管理员可见（控制器层同样不向非管理员提供数据）
 */
defined('APP_BOOT') or exit;
// 时段问候：5-11 早上、11-14 中午、其余时间（含凌晨）晚上
$greetHour = (int) date('G');
if ($greetHour >= 5 && $greetHour < 11) {
    $greetWord = '早上';
} elseif ($greetHour >= 11 && $greetHour < 14) {
    $greetWord = '中午';
} else {
    $greetWord = '晚上';
}
$greetUser = Auth::user();
?>
<div class="card dashboard-greeting">
    <h3><?php echo e($greetUser['nickname']); ?>，<?php echo $greetWord; ?>好！</h3>
    <p class="greet-welcome">欢迎访问<?php echo e(site_name()); ?></p>
</div>

<?php if (Auth::isAdmin()): ?>
<div class="stat-grid">
    <div class="stat-item"><div class="num"><?php echo (int) $stats['posts']; ?></div><div class="lab">文章总数</div></div>
    <div class="stat-item"><div class="num"><?php echo (int) $stats['published']; ?></div><div class="lab">已发布</div></div>
    <div class="stat-item"><div class="num"><?php echo (int) $stats['pendingPosts']; ?></div><div class="lab">待审核文章</div></div>
    <div class="stat-item"><div class="num"><?php echo (int) $stats['comments']; ?></div><div class="lab">已公开评论</div></div>
    <div class="stat-item"><div class="num"><?php echo (int) $stats['pendingComments']; ?></div><div class="lab">待审评论</div></div>
    <div class="stat-item"><div class="num"><?php echo (int) $stats['users']; ?></div><div class="lab">注册用户</div></div>
</div>
<?php endif; ?>

<?php if (Auth::check_cap('view_logs')): ?>
<div class="card" style="margin-top:18px">
    <h3>最近审计日志</h3>
    <div class="table-wrap">
    <table class="table">
        <tr><th>时间</th><th>用户</th><th>类别</th><th>动作</th><th>结果</th><th>IP</th></tr>
        <?php foreach ($recentLogs as $logItem): ?>
        <tr>
            <td><?php echo e($logItem['created_at']); ?></td>
            <td><?php echo (int) $logItem['user_id']; ?>（<?php echo e($logItem['role']); ?>）</td>
            <td><?php echo e($logItem['category']); ?></td>
            <td><?php echo e($logItem['action']); ?></td>
            <td><?php if ($logItem['result'] === 'success'): ?><span class="tag green">成功</span><?php else: ?><span class="tag red">失败</span><?php endif; ?></td>
            <td><?php echo e($logItem['ip']); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    </div>
    <p><a href="<?php echo e(site_base_admin('log/list')); ?>">进入日志中心 →</a></p>
</div>
<?php endif; ?>
