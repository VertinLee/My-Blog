<?php
/**
 * 后台视图：用户新增/编辑
 */
defined('APP_BOOT') or exit;
$isEdit = $user !== null;
$roles = array('user' => '用户', 'editor' => '编辑', 'admin' => '管理员');
// 安装管理员账号：角色与状态锁定，防止误降权/误禁用
$lockRoot = $isEdit && !empty($isRoot);
?>
<div class="card">
    <form method="post" action="<?php echo e(site_base_admin('user/save')); ?>">
        <?php echo Csrf::field(); ?>
        <input type="hidden" name="id" value="<?php echo $isEdit ? (int) $user['id'] : 0; ?>">

        <?php if (!$isEdit): ?>
        <div class="form-row">
            <label>用户名（3-32 位字母/数字/下划线）</label>
            <input type="text" name="username" pattern="[A-Za-z0-9_]{3,32}" required>
        </div>
        <?php else: ?>
        <div class="form-row">
            <label>用户名</label>
            <input type="text" value="<?php echo e($user['username']); ?>" disabled>
        </div>
        <?php endif; ?>

        <div class="form-row">
            <label>昵称</label>
            <input type="text" name="nickname" value="<?php echo $isEdit ? e($user['nickname']) : ''; ?>">
        </div>
        <div class="form-row">
            <label>邮箱（可空）</label>
            <input type="email" name="email" value="<?php echo $isEdit && $user['email'] !== null ? e($user['email']) : ''; ?>">
        </div>
        <div class="form-row">
            <label>手机号（可空）</label>
            <input type="text" name="phone" pattern="1[3-9][0-9]{9}" value="<?php echo $isEdit ? e($user['phone']) : ''; ?>">
        </div>
        <div class="form-row">
            <label>角色（editor/admin 仅可由管理员在此授予）<?php echo $lockRoot ? '（安装管理员，不可降权）' : ''; ?></label>
            <?php if ($lockRoot): ?>
            <input type="hidden" name="role" value="admin">
            <select disabled>
                <option selected>管理员</option>
            </select>
            <?php else: ?>
            <select name="role">
                <?php foreach ($roles as $roleKey => $roleName): ?>
                <option value="<?php echo $roleKey; ?>"
                    <?php echo $isEdit && $user['role'] === $roleKey ? 'selected' : ''; ?>><?php echo $roleName; ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
        </div>
        <?php if ($isEdit): ?>
        <div class="form-row">
            <label>账号状态</label>
            <?php if ($lockRoot): ?>
            <input type="hidden" name="status" value="1">
            <select disabled>
                <option selected>正常</option>
            </select>
            <?php else: ?>
            <select name="status">
                <option value="1" <?php echo (int) $user['status'] === 1 ? 'selected' : ''; ?>>正常</option>
                <option value="0" <?php echo (int) $user['status'] !== 1 ? 'selected' : ''; ?>>禁用</option>
            </select>
            <?php endif; ?>
        </div>
        <div class="form-row">
            <label>重置密码（留空则不修改；8-64 位、大小写/数字/特殊字符至少三类）</label>
            <input type="password" name="new_password" autocomplete="new-password">
        </div>
        <?php else: ?>
        <p class="tip">新建用户将自动生成随机初始密码，保存后仅展示一次。</p>
        <?php endif; ?>
        <div class="form-row">
            <label>
                <input type="checkbox" name="force_change" value="1">
                下次登录强制改密（配合“密码定期更换”策略使用）
            </label>
        </div>
        <button class="btn" type="submit">保存</button>
        <a class="btn gray" href="<?php echo e(site_base_admin('user/list')); ?>">返回</a>
    </form>
</div>
