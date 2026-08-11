<?php
/**
 * 后台视图：日志中心（只读；无编辑/删除入口）
 * 筛选下拉经 layui form 模块渲染；日期使用原生 date 输入
 */
defined('APP_BOOT') or exit;
?>
<div class="card">
    <form method="get" action="<?php echo e(site_base_admin('log/list')); ?>" class="layui-form filter-bar">
        <input type="hidden" name="m" value="log/list">
        <select name="category">
            <?php foreach ($categories as $catItem): ?>
            <option value="<?php echo e($catItem); ?>" <?php echo $fCategory === $catItem ? 'selected' : ''; ?>>
                <?php echo $catItem === 'all' ? '全部类别' : $catItem; ?>
            </option>
            <?php endforeach; ?>
        </select>
        <select name="result">
            <option value="all" <?php echo $fResult === 'all' ? 'selected' : ''; ?>>全部结果</option>
            <option value="success" <?php echo $fResult === 'success' ? 'selected' : ''; ?>>成功</option>
            <option value="fail" <?php echo $fResult === 'fail' ? 'selected' : ''; ?>>失败</option>
        </select>
        <input type="text" name="keyword" placeholder="动作关键词" value="<?php echo e($fKeyword); ?>" class="layui-input" style="width:140px">
        <input type="date" name="from" value="<?php echo e($fFrom); ?>" class="layui-input" style="width:150px">
        <input type="date" name="to" value="<?php echo e($fTo); ?>" class="layui-input" style="width:150px">
        <button class="layui-btn layui-btn-sm" type="submit"><i class="layui-icon layui-icon-search"></i> 筛选</button>
    </form>
    <?php
    // 导出为敏感操作：POST + CSRF + 重验当前密码；筛选条件经 action 查询串传递（后端从 GET 读取）
    $exportUrl = site_base_admin('log/export&category=' . $fCategory . '&result=' . $fResult
        . '&keyword=' . urlencode($fKeyword) . '&from=' . $fFrom . '&to=' . $fTo);
    ?>
    <form method="post" action="<?php echo e($exportUrl); ?>" class="filter-bar" style="margin-top:10px"
          data-confirm="导出审计日志前须填写当前登录密码，确认继续？">
        <?php echo Csrf::field(); ?>
        <input type="password" name="current_password" placeholder="当前密码（导出需重验）" required autocomplete="current-password" class="layui-input" style="width:220px">
        <button class="layui-btn layui-btn-sm layui-btn-primary" type="submit"><i class="layui-icon layui-icon-export"></i> 导出 CSV</button>
    </form>
    <p class="tip">共 <?php echo (int) $total; ?> 条记录。</p>
</div>

<div class="card">
    <div class="table-wrap">
    <table class="layui-table" lay-skin="line">
        <thead>
            <tr><th>ID</th><th>时间</th><th>操作者</th><th>类别</th><th>动作</th><th>结果</th><th>IP</th><th>详情</th></tr>
        </thead>
        <tbody>
        <?php if (empty($logs)): ?>
        <tr class="empty-row"><td colspan="8">暂无数据</td></tr>
        <?php endif; ?>
        <?php foreach ($logs as $log): ?>
        <tr>
            <td><?php echo (int) $log['id']; ?></td>
            <td><?php echo e($log['created_at']); ?></td>
            <td>#<?php echo (int) $log['user_id']; ?> / <?php echo e($log['role']); ?></td>
            <td><?php echo e($log['category']); ?></td>
            <td><?php echo e($log['action']); ?></td>
            <td><?php echo $log['result'] === 'success' ? '<span class="layui-badge layui-bg-green">成功</span>' : '<span class="layui-badge layui-bg-red">失败</span>'; ?></td>
            <td><?php echo e($log['ip']); ?></td>
            <td style="max-width:320px;word-break:break-all"><?php echo e(mb_substr((string) $log['detail'], 0, 120)); ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php if ($totalPages > 1): ?>
    <?php
    // 页码窗口：首尾页 + 当前页 ±2，省略号连接，避免大页数时无法翻页
    $logPageUrl = function ($p) use ($fCategory, $fResult, $fKeyword, $fFrom, $fTo) {
        return 'log/list&page=' . $p . '&category=' . $fCategory . '&result=' . $fResult
            . '&keyword=' . urlencode($fKeyword) . '&from=' . $fFrom . '&to=' . $fTo;
    };
    $nums = array();
    for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++) {
        $nums[] = $i;
    }
    $nums = array_values(array_unique(array_merge(array(1), $nums, array($totalPages))));
    ?>
    <div class="layui-box layui-laypage">
        <?php if ($page > 1): ?>
        <a href="<?php echo e(site_base_admin($logPageUrl($page - 1))); ?>">« 上一页</a>
        <?php endif; ?>
        <?php $prevNum = 0; ?>
        <?php foreach ($nums as $i): ?>
            <?php if ($i - $prevNum > 1): ?><span>…</span><?php endif; ?>
            <?php if ($i === $page): ?>
            <span class="layui-laypage-curr"><em class="layui-laypage-em"></em><em><?php echo $i; ?></em></span>
            <?php else: ?><a href="<?php echo e(site_base_admin($logPageUrl($i))); ?>"><?php echo $i; ?></a><?php endif; ?>
            <?php $prevNum = $i; ?>
        <?php endforeach; ?>
        <?php if ($page < $totalPages): ?>
        <a href="<?php echo e(site_base_admin($logPageUrl($page + 1))); ?>">下一页 »</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
