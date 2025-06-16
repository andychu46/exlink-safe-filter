=== exlink-safe-filter - External Link Security ===
Contributors: C1G
Donate link: https://blog.c1gstudio.com/
Tags: external links, security, link filtering, whitelist, blacklist, domain mask
Requires at least: 5.0
Tested up to: 6.8
Stable tag: 2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

高级外部链接安全过滤插件，提供白名单、灰名单、黑名单分类管理及多种安全防护选项。

== 描述 ==

exlink-safe-filter是一款企业级外部链接安全管理插件，专为WordPress网站设计。它通过先进的链接分类系统和灵活的安全策略，有效保护您的网站用户免受恶意链接的侵害，同时提供良好的用户体验。

主要功能：

* **多层次链接分类系统**
  - 白名单：完全信任的域名，直接显示原始链接
  - 灰名单：经过中转页但自动跳转
  - 黑名单：完全阻止访问，显示警告信息
  - 未知域名：可配置处理方式（3秒自动跳转、显示URL、显示编码URL或阻止访问）

* **全面的内容处理范围**
  - 内容类型：文章、页面、评论、产品(支持WooCommerce)
  - 元素类型：HTML链接(a标签)、纯文本URL、电子邮件地址、图片资源、其他资源(脚本、iframe等)

* **灵活的处理选项**
  - 处理时间：显示时处理(推荐)或发布/编辑时处理
  - 审计模式：保留原始URL在data-original-url属性中，便于审计
  - URL加密：支持Base64、ROT13加密或明文显示
  - 自定义重定向地址：默认为/exlink-safe-redirect/

* **高级安全特性**
  - 域名掩码功能：替换主体域名中间部分，保留首尾字符(如将www.domain.com.cn显示为www.d***n.com.cn)
  - 全面的域名验证系统
  - 自定义警告和安全消息
  - 多语言支持：中文(简体)和英文
  - 自定义CSS样式：可定制中转页和警告信息样式

* **用户友好的界面**
  - 直观的设置页面
  - 详细的选项说明
  - 响应式设计，适配各种设备

== 安装 ==

1. 从WordPress插件目录下载并安装插件，或上传`exlink-safe-filter`文件夹到`/wp-content/plugins/`目录
2. 在WordPress后台激活插件
3. 进入【设置】→【exlink-safe-filter Security】配置插件选项
4. 根据需求设置白名单、灰名单和黑名单
5. 配置安全策略和显示选项
6. 保存设置后插件自动生效

== 截图 ==

1. 安全中转页面示例
2. 过滤后的显示页面
3. 设置页面

== 常见问题 ==

= 如何添加域名到白名单？ =
在设置页面的"域名列表"选项卡中，将域名添加到白名单文本框，每行一个域名(*.example.com)。

= 如何添加域名到灰名单？ =
在设置页面的"域名列表"选项卡中，将域名添加到灰名单文本框，每行一个域名(*.example.com)。

= 如何添加域名到黑名单？ =
在设置页面的"域名列表"选项卡中，将域名添加到黑名单文本框，每行一个域名(*.example.com)。

= 如何自定义中转页样式？ =
在"自定义CSS"选项卡中，添加您的自定义CSS代码，插件会自动将其应用到中转页和警告信息。

= 插件支持哪些语言？ =
当前支持中文(简体)和英文，可在设置页面的"常规设置"中切换。

= 如何配置域名掩码功能？ =
在"安全设置"中，将"域名转码方式"设置为"打码"，系统会自动替换域名主体部分的中间字符为星号。

== 升级日志 ==

= 2.0 =
* 新增域名打码功能，支持替换主体域名中间部分
* 添加多语言支持，支持中英文切换
* 优化CSS样式，使用单行CSS元素
* 修复语言切换功能bug
* 改进中转页URL显示逻辑

= 1.0 =
* 初始版本发布
* 基本链接分类功能
* 白名单、灰名单、黑名单管理
* 自定义中转页

== 额外信息 ==

* 插件开发：C1G Studio
* 作者网站：https://blog.c1gstudio.com
* 支持邮箱：service@c1gstudio.com
* 许可证：GPLv2或更高版本