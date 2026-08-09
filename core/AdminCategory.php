<?php
/**
 * 后台分类管理（仅管理员，能力点 manage_categories）
 */
defined('APP_BOOT') or exit;

class AdminCategory
{
    /** 分类列表 */
    public static function listAction()
    {
        Auth::require_cap('manage_categories');
        $categories = DB::query('categories')->orderBy('sort', 'ASC')->orderBy('id', 'ASC')->select();
        Admin::render('分类管理', 'category_list', array('categories' => $categories));
    }

    /** 新增/编辑分类 */
    public static function saveAction()
    {
        Auth::require_cap('manage_categories');
        $id = input_int('id', 0, 'post');
        $name = input_text('name', '', 64, 'post');
        $slug = input_slug('slug', '', 'post');
        $description = input_text('description', '', 255, 'post');
        $sort = input_int('sort', 0, 'post');

        if ($name === '') {
            flash_set('error', '分类名称不能为空');
            redirect(site_base_admin('category/list'));
        }
        if ($slug === '') {
            flash_set('error', 'slug 不能为空（仅限小写字母、数字、连字符）');
            redirect(site_base_admin('category/list'));
        }
        // slug 唯一性
        $q = DB::query('categories')->where('slug', '=', $slug);
        if ($id > 0) {
            $q->where('id', '!=', $id);
        }
        if ($q->value('id')) {
            flash_set('error', 'slug 已被占用');
            redirect(site_base_admin('category/list'));
        }

        $data = array('name' => $name, 'slug' => $slug, 'description' => $description, 'sort' => $sort);
        if ($id > 0) {
            DB::update('categories', $data, array('id' => $id));
            blog_log('setting', 'category.update', 'success', array('category_id' => $id, 'name' => $name));
        } else {
            $id = DB::insert('categories', $data);
            blog_log('setting', 'category.create', 'success', array('category_id' => $id, 'name' => $name));
        }
        flash_set('success', '保存成功');
        redirect(site_base_admin('category/list'));
    }

    /** 删除分类（分类下仍有文章时禁止） */
    public static function deleteAction()
    {
        Auth::require_cap('manage_categories');
        $id = input_int('id', 0, 'post');
        if ($id > 0) {
            $used = DB::query('posts')->where('category_id', '=', $id)->count();
            if ($used > 0) {
                flash_set('error', '该分类下仍有 ' . $used . ' 篇文章，请先移动文章后再删除');
            } else {
                DB::delete('categories', array('id' => $id));
                blog_log('setting', 'category.delete', 'success', array('category_id' => $id));
                flash_set('success', '分类已删除');
            }
        }
        redirect(site_base_admin('category/list'));
    }
}
