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
        Admin::render(admin_t('admin.menu.category'), 'category_list', array('categories' => $categories));
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
            flash_set('error', admin_t('admin.category.name_required'));
            redirect(site_base_admin('category/list'));
        }
        if ($slug === '') {
            flash_set('error', admin_t('admin.category.slug_required'));
            redirect(site_base_admin('category/list'));
        }
        // slug 唯一性
        $q = DB::query('categories')->where('slug', '=', $slug);
        if ($id > 0) {
            $q->where('id', '!=', $id);
        }
        if ($q->value('id')) {
            flash_set('error', admin_t('admin.category.slug_taken'));
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
        flash_set('success', admin_t('admin.post.saved'));
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
                flash_set('error', admin_t('admin.category.in_use', array($used)));
            } else {
                DB::delete('categories', array('id' => $id));
                blog_log('setting', 'category.delete', 'success', array('category_id' => $id));
                flash_set('success', admin_t('admin.category.deleted'));
            }
        }
        redirect(site_base_admin('category/list'));
    }
}
