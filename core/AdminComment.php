<?php
/**
 * 后台评论管理：列表/审核/删除（仅管理员，能力点 manage_comments）
 */
defined('APP_BOOT') or exit;

class AdminComment
{
    /** 评论列表 */
    public static function listAction()
    {
        Auth::require_cap('manage_comments');
        $status = input_enum('status', array('all', 'published', 'pending', 'trash'), 'all', 'get');
        $page = max(1, input_int('page', 1, 'get'));
        $perPage = 20;

        $query = DB::query('comments');
        if ($status !== 'all') {
            $query->where('status', '=', $status);
        }
        $total = $query->count();
        $totalPages = max(1, (int) ceil($total / $perPage));
        // 越界页码钳制到末页，避免空表格
        $page = min($page, $totalPages);

        $q = DB::query('comments');
        if ($status !== 'all') {
            $q->where('status', '=', $status);
        }
        $comments = $q->orderBy('id', 'DESC')->limit($perPage, ($page - 1) * $perPage)->select();

        // 用户与文章映射
        $users = array();
        $rows = DB::query('users')->select(array('id', 'nickname'));
        foreach ($rows as $r) {
            $users[(int) $r['id']] = $r['nickname'];
        }
        $posts = array();
        $rows = DB::query('posts')->select(array('id', 'title'));
        foreach ($rows as $r) {
            $posts[(int) $r['id']] = $r['title'];
        }

        Admin::render('评论管理', 'comment_list', array(
            'comments' => $comments, 'status' => $status, 'page' => $page,
            'totalPages' => $totalPages, 'users' => $users, 'posts' => $posts,
        ));
    }

    /** 评论审核（通过/驳回） */
    public static function auditAction()
    {
        Auth::require_cap('moderate_comments');
        $id = input_int('id', 0, 'post');
        $decision = input_enum('decision', array('approve', 'reject'), '', 'post');
        if ($id > 0 && $decision !== '') {
            $newStatus = $decision === 'approve' ? 'published' : 'trash';
            DB::update('comments', array('status' => $newStatus), array('id' => $id));
            blog_log('comment', 'comment.audit', 'success', array('comment_id' => $id, 'decision' => $decision));
            flash_set('success', $decision === 'approve' ? '评论已通过' : '评论已驳回');
        }
        redirect(site_base_admin('comment/list&status=pending'));
    }

    /** 评论移入回收站 */
    public static function deleteAction()
    {
        Auth::require_cap('manage_comments');
        $id = input_int('id', 0, 'post');
        if ($id > 0) {
            DB::update('comments', array('status' => 'trash'), array('id' => $id));
            blog_log('comment', 'comment.delete', 'success', array('comment_id' => $id));
            flash_set('success', '评论已移入回收站');
        }
        redirect(site_base_admin('comment/list'));
    }

    /** 回收站评论恢复 */
    public static function restoreAction()
    {
        Auth::require_cap('manage_comments');
        $id = input_int('id', 0, 'post');
        if ($id > 0) {
            DB::update('comments', array('status' => 'published'), array('id' => $id, 'status' => 'trash'));
            blog_log('comment', 'comment.restore', 'success', array('comment_id' => $id));
            flash_set('success', '评论已恢复');
        }
        redirect(site_base_admin('comment/list&status=trash'));
    }
}
