<?php
/**
 * Plugin Name: 评论管制
 * Description: 文章级评论三态控制：开放 / 关闭（隐藏评论区并拦截全部评论请求）/ 禁止（保留已有评论只读，不接受新增与改删）
 * Version: 1.0.0
 * Author: Blog Team
 * Requires: 1.0
 */
defined('APP_BOOT') or exit;

/*
 * 状态说明：
 * - open（默认，无记录即 open）：评论正常
 * - closed：前台不渲染评论区（列表/发表框/操作），并拦截该文章全部评论增/改/删请求
 * - locked：已有评论保留展示（只读），隐藏发表框与本人编辑/删除按钮，拦截全部评论写请求
 * 存储：plugin_data 全局键 state_{文章id}；插件卸载时由内核统一回收
 */

/** 读取文章评论状态（未设置/非法值一律视为 open） */
function comment_guard_state($postId)
{
    $state = plugin_data_get('comment-guard', 'state_' . (int) $postId, 'open');
    if (!in_array($state, array('open', 'closed', 'locked'), true)) {
        return 'open';
    }
    return $state;
}

/* ================= 后台：文章编辑表单注入（post_edit_fields） ================= */

add_action('post_edit_fields', 'comment_guard_edit_fields');

/** 编辑页输出三态单选组；独立页面（is_page=1）无评论区，不展示 */
function comment_guard_edit_fields($post)
{
    if ($post && (int) $post['is_page'] === 1) {
        return;
    }
    $current = $post ? comment_guard_state($post['id']) : 'open';
    $options = array(
        'open'   => '开放（正常评论）',
        'closed' => '关闭（前台隐藏评论区，拦截全部评论请求）',
        'locked' => '禁止（保留已有评论只读，不接受新增与改删）',
    );
    echo '<div class="form-row">';
    echo '<label>评论管制</label>';
    echo '<div style="display:flex;flex-direction:column;gap:6px">';
    foreach ($options as $val => $label) {
        $checked = $current === $val ? ' checked' : '';
        echo '<label style="display:flex;gap:6px;align-items:center">'
            . '<input type="radio" name="comment_guard_state" value="' . e($val) . '"' . $checked . '> '
            . e($label) . '</label>';
    }
    echo '</div>';
    echo '<p class="tip" style="margin:4px 0 0">拦截在服务端强制执行（含直接构造的请求）；前台隐藏仅为体验层。</p>';
    echo '</div>';
}

/* ================= 保存：持久化状态（post_saved） ================= */

add_action('post_saved', 'comment_guard_save');

function comment_guard_save($id, $data, $isNew)
{
    // 仅文章生效；独立页面不存状态（表单也不展示）
    if (isset($data['is_page']) && (int) $data['is_page'] === 1) {
        return;
    }
    $state = input_enum('comment_guard_state', array('open', 'closed', 'locked'), 'open', 'post');
    if ($state === 'open') {
        // 默认态不落库，避免存量文章批量产生数据行
        plugin_data_delete('comment-guard', 'state_' . (int) $id);
    } else {
        plugin_data_set('comment-guard', 'state_' . (int) $id, $state);
    }
}

/* ================= 服务端拦截：评论增/改/删统一过滤器 ================= */

add_filter('comment_write_allowed', 'comment_guard_block_write', 10);

/**
 * closed/locked 均拦截全部前台评论写请求（含作者本人的修改/删除）；
 * 后台评论管理走独立控制器，不经过该过滤器
 */
function comment_guard_block_write($allowed, $action, $postId, $commentId)
{
    if ($allowed !== true) {
        return $allowed; // 已被其它插件拒绝，保持拒绝
    }
    $state = comment_guard_state($postId);
    return $state === 'open';
}

/* ================= 前台渲染：评论区条件状态（comment_area_state） ================= */

add_filter('comment_area_state', 'comment_guard_area_state', 10);

function comment_guard_area_state($state, $post)
{
    $guard = comment_guard_state($post['id']);
    if ($guard === 'closed') {
        // 关闭：列表/发表框/操作全部隐藏（内核侧 list=false 已跳过评论查询）
        return array('list' => false, 'form' => false, 'actions' => false);
    }
    if ($guard === 'locked') {
        // 禁止：保留评论列表只读，隐藏发表框与编辑/删除入口
        return array('list' => true, 'form' => false, 'actions' => false);
    }
    return $state;
}

/* ================= 后台列表：行内状态徽标（post_list_row_actions） ================= */

add_action('post_list_row_actions', 'comment_guard_list_badge');

function comment_guard_list_badge($postItem)
{
    if ((int) $postItem['is_page'] === 1) {
        return;
    }
    $state = comment_guard_state($postItem['id']);
    if ($state === 'closed') {
        echo ' <span class="tag red">评论已关闭</span>';
    } elseif ($state === 'locked') {
        echo ' <span class="tag yellow">评论已禁止</span>';
    }
}

/* ================= 清理：文章彻底删除 / 插件卸载 ================= */

add_action('post_deleted', 'comment_guard_cleanup_post');

/** 文章彻底删除后回收对应状态行 */
function comment_guard_cleanup_post($id)
{
    plugin_data_delete('comment-guard', 'state_' . (int) $id);
}

add_action('plugin_uninstall', 'comment_guard_uninstall');

/** 卸载兜底：清理残留状态行（内核已全量回收 plugin_data，此处仅保险日志） */
function comment_guard_uninstall($slug)
{
    if ($slug === 'comment-guard') {
        plugin_log('comment-guard.uninstall', array('result' => 'success'));
    }
}
