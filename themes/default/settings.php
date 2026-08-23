<?php
/**
 * 默认主题设置清单：后台「模板管理 → 设置」据此渲染表单
 *
 * 键即存储键（options.theme_settings_{dir} JSON 内），模板经 theme_setting($key) 读取。
 * 字段定义支持：label（必填）/ type（text|textarea|checkbox|select）/
 * maxlength（text 上限）/ options（select 选项，键为存储值）/ hint（字段提示）/ default。
 */
defined('APP_BOOT') or exit;

return array(
    'first_letter' => array(
        'label'   => '文章首字下沉（仿古籍排版，仅文章详情页生效）',
        'type'    => 'checkbox',
        'hint'    => '开启后正文第一段首字放大着色；首段以图片或标题开头时不生效。',
        'default' => '1',
    ),
    'icp_number' => array(
        'label'     => 'ICP 备案号（如：京ICP备12345678号-1，链接至工信部备案系统）',
        'type'      => 'text',
        'maxlength' => 64,
        'hint'      => '展示于前台页脚版权行下方；留空则不显示，与公安备案号均填写时以「|」分隔。',
        'default'   => '',
    ),
    'gongan_number' => array(
        'label'     => '公安备案号（如：京公网安备11040102700068号，链接至公安部备案查询页）',
        'type'      => 'text',
        'maxlength' => 100,
        'hint'      => '会自动提取其中的数字编号，用于拼接公安部备案查询链接；留空则不显示。',
        'default'   => '',
    ),
);
