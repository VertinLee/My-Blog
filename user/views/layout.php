<?php
/**
 * 后台统一布局：左侧菜单（按能力点显隐）+ 顶栏 + 内容区
 * 变量由 Admin::render() 注入：$pageTitle/$menuItems/$currentM/$view
 */
defined('APP_BOOT') or exit;
$adminUser = Auth::user();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?php echo e($pageTitle); ?> - 后台管理</title>
<link rel="stylesheet" href="<?php echo e(assets_url('admin/admin.css')); ?>">
<?php do_action('admin_head'); /* 插件后台样式/脚本注入点（head 内） */ ?>
</head>
<body>
<div class="admin-layout">
    <aside class="admin-side">
        <div class="side-brand"><?php echo e(site_name()); ?><span>管理后台</span></div>
        <nav class="side-menu">
            <?php foreach ($menuItems as $item): ?>
            <?php if (!empty($item['children'])): ?>
            <?php
            // 二级菜单组：子项任一激活时展开并高亮父项
            $groupOn = false;
            foreach ($item['children'] as $child) {
                if ($currentM === $child['m'] || strpos($currentM, explode('&', $child['m'])[0] . '&') === 0) {
                    $groupOn = true;
                    break;
                }
            }
            ?>
            <details class="menu-group" <?php echo $groupOn ? 'open' : ''; ?>>
                <summary class="<?php echo $groupOn ? 'on' : ''; ?>"><?php echo e($item['name']); ?></summary>
                <?php foreach ($item['children'] as $child): ?>
                <?php $childOn = $currentM === $child['m'] || strpos($currentM, explode('&', $child['m'])[0] . '&') === 0; ?>
                <a class="sub<?php echo $childOn ? ' on' : ''; ?>" href="<?php echo e(site_base_admin($child['m'])); ?>"><?php echo e($child['name']); ?></a>
                <?php endforeach; ?>
            </details>
            <?php else: ?>
            <a class="<?php echo strpos($currentM, explode('&', $item['m'])[0]) === 0 ? 'on' : ''; ?>"
               href="<?php echo e(site_base_admin($item['m'])); ?>"><?php echo e($item['name']); ?></a>
            <?php endif; ?>
            <?php endforeach; ?>
        </nav>
    </aside>
    <div class="admin-body">
        <header class="admin-top">
            <div class="top-title"><?php echo e($pageTitle); ?></div>
            <div class="top-user">
                <?php echo e($adminUser['nickname']); ?>（<?php echo e($adminUser['role']); ?>）
                <a href="<?php echo e(Router::url('home')); ?>" target="_blank">查看站点</a>
                <form method="post" action="<?php echo e(site_base_admin('auth/logout')); ?>" style="display:inline">
                    <?php echo Csrf::field(); ?>
                    <button type="submit" class="link-btn">登出</button>
                </form>
            </div>
        </header>
        <main class="admin-main">
            <?php foreach (flash_pull() as $fm): ?>
            <div class="flash <?php echo $fm['type'] === 'success' ? 'ok' : 'err'; ?>"><?php echo e($fm['text']); ?></div>
            <?php endforeach; ?>
            <?php include APP_ROOT . '/user/views/' . $view . '.php'; ?>
        </main>
    </div>
</div>
<?php if (isset($viewScripts)): ?>
<?php foreach ($viewScripts as $scriptUrl): ?>
<script src="<?php echo e($scriptUrl); ?>"></script>
<?php endforeach; ?>
<?php endif; ?>
<?php do_action('admin_footer'); /* 插件后台脚本注入点（body 末尾） */ ?>
</body>
</html>
