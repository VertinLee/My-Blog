<?php
/**
 * 后台视图：文章编辑（Vditor Markdown 编辑器，本地化资源缺失时降级为纯文本域）
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
?>
<div class="card">
<form method="post" action="<?php echo e(site_base_admin('post/save')); ?>">
    <?php echo Csrf::field(); ?>
    <input type="hidden" name="id" value="<?php echo $post ? (int) $post['id'] : 0; ?>">

    <div class="form-row">
        <label>标题 *</label>
        <input type="text" name="title" value="<?php echo $post ? e($post['title']) : ''; ?>" required style="max-width:100%">
    </div>

    <div class="form-inline" style="margin-bottom:14px">
        <div class="form-row" style="margin:0">
            <label>中文释义（slug），可选，用于伪静态 URL</label>
            <input type="text" name="slug" value="<?php echo $post ? e($post['slug']) : ''; ?>" pattern="[a-z0-9-]*">
        </div>
        <div class="form-row" style="margin:0">
            <label>分类</label>
            <select name="category_id">
                <option value="0">未分类</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?php echo (int) $cat['id']; ?>" <?php echo $post && (int) $post['category_id'] === (int) $cat['id'] ? 'selected' : ''; ?>>
                    <?php echo e($cat['name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-row" style="margin:0">
            <label>状态</label>
            <select name="status">
                <?php foreach ($statusOptions as $optKey => $optName): ?>
                <option value="<?php echo e($optKey); ?>" <?php echo $currentStatus === $optKey ? 'selected' : ''; ?>><?php echo e($optName); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-row" style="margin:0">
            <label>&nbsp;</label>
            <label style="display:flex;gap:6px;align-items:center">
                <input type="checkbox" name="is_page" value="1" <?php echo $post && (int) $post['is_page'] === 1 ? 'checked' : ''; ?>>
                作为独立页面（不出现在文章列表）
            </label>
        </div>
    </div>

    <?php if ($postAudit && !Auth::check_cap('moderate_posts')): ?>
    <div class="form-hint" style="margin-bottom:10px">站点已开启文章审核：提交的文章需管理员审核通过后才会发布。</div>
    <?php endif; ?>

    <div class="form-row">
        <label>正文（Markdown）</label>
        <div id="vditor" style="<?php echo $vditorAvailable ? '' : 'display:none'; ?>"></div>
        <textarea name="content" id="contentArea" style="max-width:100%;min-height:360px;width:100%"
            <?php echo $vditorAvailable ? 'hidden' : ''; ?>><?php echo $post ? e($post['content']) : ''; ?></textarea>
    </div>

    <div class="form-row">
        <label>摘要（可选，留空自动截取）</label>
        <textarea name="excerpt" style="max-width:100%;width:100%;min-height:60px"><?php echo $post ? e($post['excerpt']) : ''; ?></textarea>
    </div>

    <div class="form-row">
        <label>封面图路径（可选，可用右侧上传按钮获取路径）</label>
        <div class="form-inline">
            <input type="text" name="cover" id="coverPath" value="<?php echo $post ? e($post['cover']) : ''; ?>">
            <?php if (Auth::check_cap('upload')): ?>
            <input type="file" id="coverFile" accept="image/jpeg,image/png,image/webp,image/gif" style="display:none">
            <button type="button" class="btn small gray" id="coverUploadBtn">上传封面</button>
            <?php endif; ?>
        </div>
    </div>

    <button class="btn" type="submit">保存</button>
    <a class="btn gray" href="<?php echo e(site_base_admin('post/list')); ?>">返回</a>
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
                    } else {
                        alert(resp.msg || '上传失败');
                    }
                } catch (err) { alert('上传失败'); }
            }
        };
        xhr.send(fd);
    });
})();
</script>
<?php endif; ?>
