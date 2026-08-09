<?php
/**
 * 后台视图：导航管理（前台侧边栏自定义导航项增删改）
 */
defined('APP_BOOT') or exit;
?>
<div class="card">
    <h3>侧边栏导航项</h3>
    <p class="tip">
        导航项按保存顺序展示在前台主题侧边栏「导航」分组中（位于首页与独立页面之后）。
        修改标题/链接后直接保存；勾选「删除」后保存即移除；末行填写标题与链接可追加一条新项。
        链接仅支持站内相对路径（如 <code>/index.php?r=page/about</code>）或 http(s) 地址。
    </p>
    <form method="post" action="<?php echo e(site_base_admin('setting/nav_save')); ?>">
        <?php echo Csrf::field(); ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th style="width:220px">标题</th>
                    <th>链接</th>
                    <th style="width:70px">删除</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $i => $item): ?>
                <tr>
                    <td><input type="text" name="title_<?php echo $i; ?>" value="<?php echo e($item['title']); ?>" required></td>
                    <td><input type="text" name="url_<?php echo $i; ?>" value="<?php echo e($item['url']); ?>" required></td>
                    <td><label><input type="checkbox" name="del_<?php echo $i; ?>" value="1"> 删除</label></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($items)): ?>
                <tr><td colspan="3" class="tip">暂无自定义导航项，可在下方新增。</td></tr>
                <?php endif; ?>
                <tr>
                    <td><input type="text" name="new_title" placeholder="新增导航标题（留空不新增）"></td>
                    <td><input type="text" name="new_url" placeholder="新增链接，如 /index.php?r=page/about"></td>
                    <td class="tip">新增</td>
                </tr>
                </tbody>
            </table>
        </div>
        <div style="margin-top:14px">
            <button class="btn" type="submit">保存</button>
        </div>
    </form>
</div>
