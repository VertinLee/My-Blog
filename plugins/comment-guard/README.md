# 评论管制（comment-guard）

文章级评论三态控制插件：按文章选择评论开放程度，服务端强制拦截 + 前台条件渲染双保险。

## 三种状态

| 状态 | 前台展示 | 服务端行为 |
|---|---|---|
| `open`（默认） | 评论区正常 | 评论增/改/删正常 |
| `closed` 关闭 | 评论区整体不渲染（列表/发表框/操作全隐藏） | 拦截该文章全部评论增/改/删请求 |
| `locked` 禁止 | 已有评论保留展示（只读），发表框与本人编辑/删除按钮隐藏 | 拦截全部评论写请求（含作者本人改删） |

- 状态默认 `open`：无数据记录即视为开放，默认态不落库。
- 后台「评论管理」不受影响（管理员审核/清理通道保持畅通）。
- 拦截在服务端强制执行，直接构造请求同样被拒，并记 `fail` 审计日志（`reason=write_denied`）。

## 使用

1. 后台「插件管理 → 启用」；
2. 编辑文章时表单内出现「评论管制」单选组，选择后保存即生效；
3. 文章列表行内会显示「评论已关闭 / 评论已禁止」徽标。

独立页面（is_page=1）无评论区，表单与徽标均自动跳过。

## 挂接的钩子

| 钩子 | 用途 |
|---|---|
| `post_edit_fields` | 文章编辑表单内输出三态单选组 |
| `post_saved` | 保存时持久化状态 |
| `comment_write_allowed` | 评论增/改/删统一拦截 |
| `comment_area_state` | 控制前台评论区 list/form/actions 渲染标志 |
| `post_list_row_actions` | 文章列表行内状态徽标 |
| `post_deleted` | 文章彻底删除后清理状态行 |

## 存储与清理

- 状态存 `plugin_data` 表全局键 `state_{文章id}`（值 `closed`/`locked`），不建表、不改 posts 结构；
- 文章彻底删除时经 `post_deleted` 逐条清理；插件卸载时内核统一回收全部 `plugin_data` 行。

## 依赖

依赖内核钩子：`post_edit_fields`、`post_saved`、`comment_write_allowed`、`comment_area_state`、`post_list_row_actions`、`post_deleted`（均为 1.0 新增，见 `plugins/README.md` §3）。
主题需实现 `comment_area_state` 条件渲染（默认主题已实现，见 `themes/README.md` §5.1）；旧主题未实现时仅前台展示层不隐藏，服务端拦截不受影响。
