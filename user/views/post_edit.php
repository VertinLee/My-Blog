<?php
/**
 * 后台视图：文章编辑（layui 表单 + Vditor Markdown 编辑器，本地化资源缺失时降级为纯文本域）
 */
defined('APP_BOOT') or exit;
$vditorAvailable = is_file(APP_ROOT . '/assets/vendor/vditor/dist/index.min.js');
// 新建文章默认直接发布；审核开关开启且无审核权限时默认提交审核
if ($post) {
    $currentStatus = $post['status'];
} else {
    $currentStatus = ($postAudit && !Auth::check_cap('moderate_posts')) ? 'pending' : 'published';
}
// 状态选项按审核开关与角色收敛
$statusOptions = array('draft' => '草稿');
if ($postAudit) {
    if (Auth::check_cap('moderate_posts')) {
        $statusOptions['published'] = '发布';
    } else {
        $statusOptions['pending'] = '提交审核';
    }
    if ($currentStatus === 'published' && Auth::check_cap('moderate_posts')) {
        $statusOptions['published'] = '发布';
    }
} else {
    $statusOptions['published'] = '发布';
}
if ($currentStatus === 'pending' && !isset($statusOptions['pending'])) {
    $statusOptions['pending'] = '待审核';
}
// 是否勾选了独立页面：控制“显示在侧边栏导航”项的显隐（该选项仅独立页面生效）
$isPageOn = $post && (int) $post['is_page'] === 1;
?>
<div class="card">
<form method="post" action="<?php echo e(site_base_admin('post/save')); ?>" class="layui-form v-form">
    <?php echo Csrf::field(); ?>
    <input type="hidden" name="id" value="<?php echo $post ? (int) $post['id'] : 0; ?>">

    <div class="layui-form-item">
        <label class="v-label">标题 *</label>
        <input type="text" name="title" value="<?php echo $post ? e($post['title']) : ''; ?>" required class="layui-input" style="max-width:100%">
    </div>

    <?php // 别名/分类/状态/页面选项：行内多列 ?>
    <div class="filter-bar" style="align-items:flex-start;margin-bottom:14px">
        <div>
            <label class="v-label">别名（slug），可选，用于伪静态 URL；小写字母/数字/连字符，不能为纯数字</label>
            <input type="text" name="slug" value="<?php echo $post ? e($post['slug']) : ''; ?>" pattern="[a-z0-9-]*" class="layui-input" style="width:240px">
        </div>
        <div>
            <label class="v-label">分类</label>
            <select name="category_id">
                <option value="0">未分类</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?php echo (int) $cat['id']; ?>" <?php echo $post && (int) $post['category_id'] === (int) $cat['id'] ? 'selected' : ''; ?>>
                    <?php echo e($cat['name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="v-label">状态</label>
            <select name="status">
                <?php foreach ($statusOptions as $optKey => $optName): ?>
                <option value="<?php echo e($optKey); ?>" <?php echo $currentStatus === $optKey ? 'selected' : ''; ?>><?php echo e($optName); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="padding-top:24px">
            <label style="display:flex;gap:6px;align-items:center">
                <input type="checkbox" name="is_page" value="1" <?php echo $isPageOn ? 'checked' : ''; ?>>
                作为独立页面（不出现在文章列表）
            </label>
        </div>
        <div style="padding-top:24px<?php echo $isPageOn ? '' : ';display:none'; ?>" id="showInNavRow">
            <label style="display:flex;gap:6px;align-items:center">
                <input type="checkbox" name="show_in_nav" value="1" <?php echo !empty($inNav) ? 'checked' : ''; ?>>
                显示在侧边栏导航
            </label>
        </div>
    </div>

    <?php do_action('post_edit_fields', $post); /* 插件注入点：文章表单扩展字段（新建时 $post 为 null），输出需自带 .form-row 结构并自行转义 */ ?>

    <?php if ($postAudit && !Auth::check_cap('moderate_posts')): ?>
    <div class="form-hint" style="margin-bottom:10px">站点已开启文章审核：提交的文章需管理员审核通过后才会发布。</div>
    <?php endif; ?>

    <div class="layui-form-item">
        <label class="v-label">正文（Markdown）</label>
        <div id="vditor" style="<?php echo $vditorAvailable ? '' : 'display:none'; ?>"></div>
        <textarea name="content" id="contentArea" class="layui-textarea" style="max-width:100%;min-height:360px;width:100%"
            <?php echo $vditorAvailable ? 'hidden' : ''; ?>><?php echo $post ? e($post['content']) : ''; ?></textarea>
    </div>

    <div class="layui-form-item">
        <label class="v-label">摘要（可选，留空自动截取）</label>
        <textarea name="excerpt" class="layui-textarea" style="max-width:100%;width:100%;min-height:60px"><?php echo $post ? e($post['excerpt']) : ''; ?></textarea>
    </div>

    <div class="layui-form-item">
        <label class="v-label">封面图路径（可选，可用右侧上传按钮获取路径）</label>
        <div class="filter-bar">
            <input type="text" name="cover" id="coverPath" value="<?php echo $post ? e($post['cover']) : ''; ?>" class="layui-input" style="width:320px">
            <?php if (Auth::check_cap('upload')): ?>
            <input type="file" id="coverFile" accept="image/jpeg,image/png,image/webp,image/gif" style="display:none">
            <button type="button" class="layui-btn layui-btn-primary" id="coverUploadBtn">
                <i class="layui-icon layui-icon-upload"></i> 上传封面
            </button>
            <?php endif; ?>
        </div>
    </div>

    <button class="layui-btn" type="submit"><i class="layui-icon layui-icon-ok"></i> 保存</button>
    <a class="layui-btn layui-btn-primary" href="<?php echo e(site_base_admin('post/list')); ?>">返回</a>
</form>
</div>

<?php if ($vditorAvailable): ?>
<link rel="stylesheet" href="<?php echo e(assets_url('vendor/vditor/dist/index.css')); ?>">
<script src="<?php echo e(assets_url('vendor/vditor/dist/index.min.js')); ?>"></script>
<script>
(function () {
    var area = document.getElementById('contentArea');
    window.cbVditor = new Vditor('vditor', {
        // 必须指定本地 cdn：Vditor 默认从 unpkg CDN 懒加载 lute/图标等子资源，违反禁 CDN 约束
        cdn: <?php echo json_encode(assets_url('vendor/vditor')); ?>,
        height: 420,
        mode: 'ir',
        value: area.value,
        cache: { enable: false },
        preview: { math: { engine: 'katex' } },
        toolbarConfig: { pin: true },
        upload: {
            url: <?php echo json_encode(site_base_admin('upload/image')); ?>,
            fieldName: 'file',
            extraData: { _csrf: <?php echo json_encode(Csrf::token()); ?> },
            max: 2 * 1024 * 1024,
            accept: 'image/jpeg,image/png,image/webp,image/gif',
            format: function (files, responseText) {
                var resp = JSON.parse(responseText);
                if (resp.code === 0) {
                    return JSON.stringify({ code: 0, data: { succMap: (function () {
                        var m = {};
                        for (var i = 0; i < files.length; i++) { m[files[i].name] = resp.data.url; }
                        return m;
                    })() } });
                }
                return JSON.stringify({ code: 1, msg: resp.msg || '上传失败' });
            }
        },
        input: function (value) { area.value = value; }
    });
    // 提交前确保内容同步
    area.form.addEventListener('submit', function () {
        if (window.cbVditor && window.cbVditor.getValue) {
            area.value = window.cbVditor.getValue();
        }
    });
})();
</script>
<?php endif; ?>

<?php if (Auth::check_cap('upload')): ?>
<script>
(function () {
    var btn = document.getElementById('coverUploadBtn');
    var fileInput = document.getElementById('coverFile');
    if (!btn || !fileInput) { return; }
    // 反馈优先用 layui layer.msg（admin.js 暴露于 CB_ADMIN），缺失时回退 alert
    function feedback(msg, ok) {
        if (window.CB_ADMIN && CB_ADMIN.layer) {
            CB_ADMIN.layer.msg(msg, { icon: ok ? 1 : 2 });
        } else {
            window.alert(msg);
        }
    }
    btn.addEventListener('click', function () { fileInput.click(); });
    fileInput.addEventListener('change', function () {
        if (!fileInput.files.length) { return; }
        var fd = new FormData();
        fd.append('file', fileInput.files[0]);
        fd.append('_csrf', <?php echo json_encode(Csrf::token()); ?>);
        var xhr = new XMLHttpRequest();
        xhr.open('POST', <?php echo json_encode(site_base_admin('upload/image')); ?>, true);
        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4) {
                try {
                    var resp = JSON.parse(xhr.responseText);
                    if (resp.code === 0) {
                        document.getElementById('coverPath').value = resp.data.url;
                        feedback('封面上传成功', true);
                    } else {
                        feedback(resp.msg || '上传失败', false);
                    }
                } catch (err) { feedback('上传失败', false); }
            }
        };
        xhr.send(fd);
    });
})();
</script>
<?php endif; ?>

<script>
(function () {
    // “显示在侧边栏导航”仅对独立页面生效：取消勾选独立页面时隐藏该项并清除勾选
    //（后端对非独立页面传入该选项同样直接忽略）
    var pageBox = document.querySelector('input[name="is_page"]');
    var navRow = document.getElementById('showInNavRow');
    if (!pageBox || !navRow) { return; }
    var navBox = navRow.querySelector('input[name="show_in_nav"]');
    function syncNavRow() {
        navRow.style.display = pageBox.checked ? '' : 'none';
        if (!pageBox.checked && navBox) { navBox.checked = false; }
    }
    pageBox.addEventListener('change', syncNavRow);
})();
</script>
