<?php
/**
 * 后台视图：日志中心（只读；无编辑/删除入口）
 * 筛选下拉经 layui form 模块渲染；日期范围经 layui laydate 选择，
 * 选中后由 admin.js 拆回隐藏域 from/to 提交（后端契约不变），
 * 无 JS 时隐藏域保持当前筛选值原样提交
 */
defined('APP_BOOT') or exit;
// 范围框回显文本：双侧均有值时拼为范围，否则回显已有单侧值
if ($fFrom !== '' && $fTo !== '') {
    $rangeText = $fFrom . ' ~ ' . $fTo;
} elseif ($fFrom !== '') {
    $rangeText = $fFrom;
} else {
    $rangeText = $fTo;
}
?>
<div class="card">
    <form method="get" action="<?php echo e(site_base_admin('log/list')); ?>" class="layui-form filter-bar">
        <input type="hidden" name="m" value="log/list">
        <select name="category">
            <?php foreach ($categories as $catItem): ?>
            <option value="<?php echo e($catItem); ?>" <?php echo $fCategory === $catItem ? 'selected' : ''; ?>>
                <?php echo $catItem === 'all' ? e(admin_t('admin.log.all_categories')) : e($catItem); ?>
            </option>
            <?php endforeach; ?>
        </select>
        <select name="result">
            <option value="all" <?php echo $fResult === 'all' ? 'selected' : ''; ?>><?php echo e(admin_t('admin.log.all_results')); ?></option>
            <option value="success" <?php echo $fResult === 'success' ? 'selected' : ''; ?>><?php echo e(admin_t('admin.log.success')); ?></option>
            <option value="fail" <?php echo $fResult === 'fail' ? 'selected' : ''; ?>><?php echo e(admin_t('admin.log.fail')); ?></option>
        </select>
        <input type="text" name="keyword" placeholder="<?php echo e(admin_t('admin.log.keyword_ph')); ?>" value="<?php echo e($fKeyword); ?>" class="layui-input" style="width:140px">
        <?php // 日期范围选择：laydate 渲染（.cb-date-range 由 admin.js 统一接管），提交值仍走 from/to 隐藏域 ?>
        <input type="hidden" name="from" id="logDateFrom" value="<?php echo e($fFrom); ?>">
        <input type="hidden" name="to" id="logDateTo" value="<?php echo e($fTo); ?>">
        <input type="text" class="layui-input cb-date-range" data-from="#logDateFrom" data-to="#logDateTo"
               placeholder="<?php echo e(admin_t('admin.log.date_range_ph')); ?>" autocomplete="off" style="width:230px"
               value="<?php echo e($rangeText); ?>">
        <button class="layui-btn layui-btn-sm" type="submit"><i class="layui-icon layui-icon-search"></i> <?php echo e(admin_t('admin.comment.filter_btn')); ?></button>
    </form>
    <?php
    // 导出为敏感操作：POST + CSRF + 重验当前密码；筛选条件经 action 查询串传递（后端从 GET 读取）
    $exportUrl = site_base_admin('log/export&category=' . $fCategory . '&result=' . $fResult
        . '&keyword=' . urlencode($fKeyword) . '&from=' . $fFrom . '&to=' . $fTo);
    ?>
    <form method="post" action="<?php echo e($exportUrl); ?>" class="filter-bar" style="margin-top:10px"
          data-confirm="<?php echo e(admin_t('admin.log.export_confirm')); ?>">
        <?php echo Csrf::field(); ?>
        <input type="password" name="current_password" placeholder="<?php echo e(admin_t('admin.log.export_pwd_ph')); ?>" required autocomplete="current-password" class="layui-input" style="width:220px">
        <button class="layui-btn layui-btn-sm layui-btn-primary" type="submit"><i class="layui-icon layui-icon-export"></i> <?php echo e(admin_t('admin.log.export_btn')); ?></button>
    </form>
    <p class="tip"><?php echo e(admin_t('admin.log.total', array((int) $total))); ?></p>
</div>

<div class="card">
    <div class="table-wrap">
    <table class="layui-table" lay-skin="line">
        <thead>
            <tr><th>ID</th><th><?php echo e(admin_t('admin.log.col_time')); ?></th><th><?php echo e(admin_t('admin.log.col_operator')); ?></th><th><?php echo e(admin_t('admin.log.col_category')); ?></th><th><?php echo e(admin_t('admin.log.col_action')); ?></th><th><?php echo e(admin_t('admin.log.col_result')); ?></th><th>IP</th><th><?php echo e(admin_t('admin.log.col_detail')); ?></th></tr>
        </thead>
        <tbody>
        <?php if (empty($logs)): ?>
        <tr class="empty-row"><td colspan="8"><?php echo e(admin_t('admin.common.none')); ?></td></tr>
        <?php endif; ?>
        <?php foreach ($logs as $log): ?>
        <tr>
            <td><?php echo (int) $log['id']; ?></td>
            <td><?php echo e($log['created_at']); ?></td>
            <td>#<?php echo (int) $log['user_id']; ?> / <?php echo e($log['role']); ?></td>
            <td><?php echo e($log['category']); ?></td>
            <td><?php echo e($log['action']); ?></td>
            <td><?php echo $log['result'] === 'success' ? '<span class="layui-badge layui-bg-green">' . e(admin_t('admin.log.success')) . '</span>' : '<span class="layui-badge layui-bg-red">' . e(admin_t('admin.log.fail')) . '</span>'; ?></td>
            <td><?php echo e($log['ip']); ?></td>
            <?php // min-width 保底：break-all 使 min-content 缩到 1 字符，窄屏会被其他列挤到只剩几字宽；超出走 table-wrap 横向滚动 ?>
            <td style="min-width:200px;max-width:320px;word-break:break-all"><?php echo e(mb_substr((string) $log['detail'], 0, 120)); ?></td>
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
        <a href="<?php echo e(site_base_admin($logPageUrl($page - 1))); ?>">« <?php echo e(admin_t('admin.common.page_prev')); ?></a>
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
        <a href="<?php echo e(site_base_admin($logPageUrl($page + 1))); ?>"><?php echo e(admin_t('admin.common.page_next')); ?> »</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
