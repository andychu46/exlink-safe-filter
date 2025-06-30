<?php
/*
Plugin Name: exlink-safe-filter - External Link Security
Plugin URI: https://github.com/andychu46/exlink-safe-filter
Description: Advanced external link filtering with whitelist, greylist, blacklist and multiple security options.
Version: 2.0.4
Requires at least: 5.0
Tested up to: 6.8
Requires PHP:   7.2
Author: C1G
Author URI:  https://blog.c1gstudio.com
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.txt
Text Domain: exlink-safe-filter
*/

// 防止直接访问
defined('ABSPATH') or die('No direct access allowed!');

class ExLinkFilter {

    private $default_settings = [
        'language' => 'en_US', // Default language: en_US or zh_CN
        'enabled' => 0, // 默认不启用
        'processing_time' => 'display',
        'scope_content' => ['post'], // 默认仅选中文章
        'scope_elements' => ['html_links'], // 默认仅选中HTML链接
        'redirect_slug' => 'exlink-safe-redirect',
        'whitelist' => '',
        'greylist' => '',
        'blacklist' => '',
        'audit_mode' => 0,
        'unknown_action' => 'redirect_3s',
        'unknown_message' => 'You are being redirected to an external site...',
        'warning_message' => 'This link has been blocked for security reasons',
        'encryption' => 'base64',
        'custom_css' => '.exlink-warning { background: #fff8e5; padding: 15px; border: 1px solid #ffd699; border-radius: 4px; margin: 10px 0; }',
        'operation_mode' => 'blacklist',
        'intermediate_page_transcode' => 'none',
        'intermediate_transcode_custom_text' => '[link]',
        'domain_transcode' => 'none',
        'domain_transcode_custom_text' => '[link]',
        'allow_index' => 0,
        'show_footer' => 0
    ];

    public function __construct() {
        // 加载语言文件
        add_action('plugins_loaded', [$this, 'load_textdomain']);
        
        // 初始化设置
        add_action('admin_init', [$this, 'init_settings']);
        
        // 创建设置菜单
        add_action('admin_menu', [$this, 'create_admin_menu']);
        
        // 添加插件设置链接
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'add_plugin_action_links']);
        
        // 注册激活/停用钩子
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
        
        // 处理内容过滤
        $this->register_content_filters();
        
        // 处理重定向
        add_action('init', [$this, 'add_rewrite_rule']);
        add_action('template_redirect', [$this, 'handle_redirect']);
    }
    
    /**
     * 加载插件语言文件
     */
    public function load_textdomain() {
        $settings = $this->get_settings();
        $locale = $settings['language'];
        $domain = 'exlink-safe-filter';
        $mofile = $domain . '-' . $locale . '.mo';
        $mofile_path = plugin_dir_path(__FILE__) . 'languages/' . $mofile;
        
        // 加载指定语言文件
        load_textdomain($domain, $mofile_path);
    }

    // 注册内容过滤器
    private function register_content_filters() {
        $settings = $this->get_settings();
        
        // 根据设置的处理时间添加过滤器
        if ($settings['processing_time'] === 'display') {
            // 显示时处理
            if (in_array('post', $settings['scope_content'])) {
                add_filter('the_content', [$this, 'filter_content']);
            }
            if (in_array('page', $settings['scope_content'])) {
                add_filter('the_content', [$this, 'filter_content']);
            }
            if (in_array('comment', $settings['scope_content'])) {
                add_filter('comment_text', [$this, 'filter_content']);
            }
            if (in_array('product', $settings['scope_content'])) {
                add_filter('woocommerce_short_description', [$this, 'filter_content']);
                add_filter('the_content', [$this, 'filter_content']);
            }
        } else {
            // 发布时处理
            add_filter('content_save_pre', [$this, 'filter_content_pre_save'], 10, 1);
        }
    }

    // 激活插件
    public function activate() {
        // 保存默认设置
        if (false === get_option('exlink_settings')) {
            update_option('exlink_settings', $this->default_settings);
        }
        
        // 添加重写规则
        $this->add_rewrite_rule();
        flush_rewrite_rules();
        
        // 自动将站点域名添加到白名单
        $site_url = get_option('siteurl');
        $domain = $this->get_domain_from_url($site_url);
        
        $settings = $this->get_settings();
        $whitelist = array_filter(array_map('trim', explode("\n", $settings['whitelist'])));
        
        if (!in_array($domain, $whitelist)) {
            $whitelist[] = $domain;
            $settings['whitelist'] = implode("\n", $whitelist) . "\n";
            update_option('exlink_settings', $settings);
        }
        
        // 添加自定义CSS
        $this->add_custom_css($settings['custom_css']);
    }

    // 停用插件
    public function deactivate() {
        flush_rewrite_rules();
        
    }

    // 添加重写规则
    public function add_rewrite_rule() {
        $settings = $this->get_settings();
        $slug = sanitize_title($settings['redirect_slug']);
        
        add_rewrite_rule('^' . $slug . '/?$', 'index.php?exlink_redirect=1', 'top');
        add_rewrite_tag('%exlink_redirect%', '1');
    }

    // 初始化设置
    public function init_settings() {
        register_setting('exlink_settings_group', 'exlink_settings', [$this, 'sanitize_settings']);
    }

    // 设置页面清理
    public function sanitize_settings($input) {
        // 处理恢复默认设置
        if (!empty($_POST['reset_defaults'])) {
            return $this->default_settings;
        }
        $output = [];
        
        // 处理复选框数组
        $checkbox_fields = ['scope_content', 'scope_elements'];
        foreach ($checkbox_fields as $field) {
            $output[$field] = isset($input[$field]) ? (array) $input[$field] : [];
        }
        
        // 处理文本字段
        $text_fields = [
            'redirect_slug', 'unknown_message', 'warning_message', 'custom_css', 'language'
        ];
        foreach ($text_fields as $field) {
            $output[$field] = isset($input[$field]) ? sanitize_text_field($input[$field]) : '';
        }

        // 运行模式设置
        $output['operation_mode'] = in_array($input['operation_mode'], ['blacklist', 'whitelist']) ? $input['operation_mode'] : 'blacklist';
        // 中转页转码设置
        $output['intermediate_page_transcode'] = in_array($input['intermediate_page_transcode'], ['none', 'mask', 'entity', 'custom']) ? $input['intermediate_page_transcode'] : 'none';
        $output['intermediate_transcode_custom_text'] = sanitize_text_field($input['intermediate_transcode_custom_text']);
        
        // 域名转码设置
        $output['domain_transcode'] = in_array($input['domain_transcode'], ['none', 'mask', 'entity', 'custom']) ? $input['domain_transcode'] : 'none';
        $output['domain_transcode_custom_text'] = sanitize_text_field($input['domain_transcode_custom_text']);
        $output['allow_index'] = isset($input['allow_index']) ? 1 : 0;
        $output['show_footer'] = isset($input['show_footer']) ? 1 : 0;
        $output['whitelist'] = sanitize_textarea_field($input['whitelist']);
        $output['greylist'] = sanitize_textarea_field($input['greylist']);
        $output['blacklist'] = sanitize_textarea_field($input['blacklist']);
        
        // 处理单选按钮
        $radio_fields = ['processing_time', 'unknown_action', 'encryption'];
        foreach ($radio_fields as $field) {
            $output[$field] = isset($input[$field]) ? $input[$field] : $this->default_settings[$field];
        }
        
        // 处理开关
        $output['enabled'] = isset($input['enabled']) ? 1 : 0;
        $output['audit_mode'] = isset($input['audit_mode']) ? 1 : 0;
        
        // 添加自定义CSS
        if (!empty($output['custom_css'])) {
            $this->add_custom_css($output['custom_css']);
        }
        
        return $output;
    }

    // 添加自定义CSS
    private function add_custom_css($css) {
        if (!empty($css)) {
            update_option('exlink_custom_css', wp_strip_all_tags($css));
        }
    }



    // 创建设置菜单
    public function create_admin_menu() {
        add_options_page(
            'exlink-safe-filter Settings',
            'exlink-safe-filter Security',
            'manage_options',
            'exlink-settings',
            [$this, 'render_settings_page']
        );
    }
    
    /**
     * 添加插件操作链接
     * 
     * @param array $links 现有链接数组
     * @return array 修改后的链接数组
     */
    public function add_plugin_action_links($links) {
        $settings_link = '<a href="' . admin_url('options-general.php?page=exlink-settings') . '">' . __('Settings', 'exlink-safe-filter') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    // 渲染设置页面
    public function render_settings_page() {
        $settings = $this->get_settings();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('exlink-safe-filter - External Link Security Settings', 'exlink-safe-filter'); ?></h1>
        
        <form method="post" action="options.php">
                <?php settings_fields('exlink_settings_group'); ?>
                <?php do_settings_sections('exlink_settings_group'); ?>
                
                <h2 class="title"><?php esc_html_e('General Settings', 'exlink-safe-filter'); ?></h2>
                <table class="form-table">
                    <tr>
            <th scope="row"><?php esc_html_e('Language', 'exlink-safe-filter'); ?></th>
            <td>
                <select name="exlink_settings[language]">
                    <option value="en_US" <?php selected($settings['language'], 'en_US'); ?>><?php esc_html_e('English', 'exlink-safe-filter'); ?></option>
                    <option value="zh_CN" <?php selected($settings['language'], 'zh_CN'); ?>><?php esc_html_e('Chinese (Simplified)', 'exlink-safe-filter'); ?></option>
                </select>
            </td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e('Enable exLink', 'exlink-safe-filter'); ?></th>
            <td>
                <label>
                    <input type="checkbox" name="exlink_settings[enabled]" value="1" <?php checked($settings['enabled'], 1); ?>>
                    <?php esc_html_e('Activate link filtering', 'exlink-safe-filter'); ?>
                </label>
            </td>
        </tr>
                    
        <tr>
            <th scope="row"><?php esc_html_e('Processing Time', 'exlink-safe-filter'); ?></th>
            <td>
                <label>
                    <input type="radio" name="exlink_settings[processing_time]" value="display" <?php checked($settings['processing_time'], 'display'); ?>>
                    <?php esc_html_e('Process when content is displayed (recommended)', 'exlink-safe-filter'); ?>
                </label>
                <!--
                <br>
                <label>
                    <input type="radio" name="exlink_settings[processing_time]" value="save" <?php checked($settings['processing_time'], 'save'); ?>>
                    <?php esc_html_e('Process when content is saved', 'exlink-safe-filter'); ?>
                </label>
                -->
            </td>
        </tr>

                        <th scope="row"><?php esc_html_e('Operation Mode', 'exlink-safe-filter'); ?></th>
                        <td>
                            <label>
                                <input type="radio" name="exlink_settings[operation_mode]" value="blacklist" <?php checked($settings['operation_mode'], 'blacklist'); ?>>
                                <?php esc_html_e('Blacklist Mode', 'exlink-safe-filter'); ?>
                            </label><br>
                            <p class="description"><?php esc_html_e('Only domains in blacklist will be processed through intermediate page', 'exlink-safe-filter'); ?></p><br>
                            <label>
                                <input type="radio" name="exlink_settings[operation_mode]" value="whitelist" <?php checked($settings['operation_mode'], 'whitelist'); ?>>
                                <?php esc_html_e('Whitelist Mode', 'exlink-safe-filter'); ?>
                            </label><br>
                            <p class="description"><?php esc_html_e('Only domains NOT in whitelist will be processed through intermediate page', 'exlink-safe-filter'); ?></p>
                        </td>
                    </tr>                   
                    <tr>
                        <th scope="row"><?php esc_html_e('Content Scope', 'exlink-safe-filter'); ?></th>
                        <td>
                            <?php $scopes = [
                                'post' => __('Posts', 'exlink-safe-filter'), 
                                'page' => __('Pages', 'exlink-safe-filter'), 
                                'comment' => __('Comments', 'exlink-safe-filter'), 
                                'product' => __('Products', 'exlink-safe-filter')
                            ]; ?>
                            <?php foreach ($scopes as $key => $label): ?>
                                <label>
                                    <input type="checkbox" name="exlink_settings[scope_content][]" value="<?php echo esc_html($key); ?>" <?php checked(in_array($key, $settings['scope_content'])); ?>>
                                    <?php echo esc_html($label); ?>
                                </label><br>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e('Element Scope', 'exlink-safe-filter'); ?></th>
                        <td>
                            <?php $elements = [
                                'html_links' => __('HTML Links (a tags)', 'exlink-safe-filter'), 
                                'plaintext' => __('Plaintext URLs', 'exlink-safe-filter'), 
                                'emails' => __('Email Addresses', 'exlink-safe-filter'), 
                                'images' => __('Images (img tags)', 'exlink-safe-filter'), 
                                'other' => __('Other Resources (scripts, iframes, etc)', 'exlink-safe-filter')
                            ]; ?>
                            <?php foreach ($elements as $key => $label): ?>
                                <label>
                                    <input type="checkbox" name="exlink_settings[scope_elements][]" value="<?php echo esc_html($key); ?>" <?php checked(in_array($key, $settings['scope_elements'])); ?>>
                                    <?php echo esc_html($label); ?>
                                </label><br>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e('Redirect Slug', 'exlink-safe-filter'); ?></th>
                        <td>
                            <input type="text" name="exlink_settings[redirect_slug]" value="<?php echo esc_attr($settings['redirect_slug']); ?>" class="regular-text">
                            <p class="description"><?php esc_html_e('Default: exlink-safe-redirect (will create /exlink-safe-redirect/ URL)', 'exlink-safe-filter'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Domain Transcoding', 'exlink-safe-filter'); ?></th>
                        <td>
                            <select name="exlink_settings[domain_transcode]" id="domain_transcode">
                                <option value="none" <?php selected($settings['domain_transcode'], 'none'); ?>><?php esc_html_e('No Transcoding', 'exlink-safe-filter'); ?></option>
                                <option value="mask" <?php selected($settings['domain_transcode'], 'mask'); ?>><?php esc_html_e('Mask Domain (e.g. ex***le.com)', 'exlink-safe-filter'); ?></option>
                                <option value="entity" <?php selected($settings['domain_transcode'], 'entity'); ?>><?php esc_html_e('HTML Entity Encoding', 'exlink-safe-filter'); ?></option>
                                <option value="custom" <?php selected($settings['domain_transcode'], 'custom'); ?>><?php esc_html_e('Replace with Custom Text', 'exlink-safe-filter'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('How to display domain names in original page links', 'exlink-safe-filter'); ?></p>
                            <div id="domain_transcode_text" style="margin-top:10px; display:<?php echo $settings['domain_transcode'] === 'custom' ? 'block' : 'none'; ?>">
                                <input type="text" name="exlink_settings[domain_transcode_custom_text]" value="<?php echo esc_attr($settings['domain_transcode_custom_text']); ?>" class="regular-text">
                                <p class="description"><?php esc_html_e('Custom text to replace domain name in original page', 'exlink-safe-filter'); ?></p>
                            </div>
                        </td>
                    </tr>                   
                    <tr>
                        <th scope="row"><?php esc_html_e('Audit Mode', 'exlink-safe-filter'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="exlink_settings[audit_mode]" value="1" <?php checked($settings['audit_mode'], 1); ?>>
                                <?php esc_html_e('Preserve original URL in data-original-url attribute', 'exlink-safe-filter'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                
                <h2 class="title"><?php esc_html_e('Domain Lists', 'exlink-safe-filter'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Whitelisted Domains', 'exlink-safe-filter'); ?></th>
                        <td>
                            <textarea name="exlink_settings[whitelist]" rows="5" cols="50" class="large-text"><?php echo esc_textarea($settings['whitelist']); ?></textarea>
                            <p class="description"><?php esc_html_e('One domain per line (e.g. *.example.com). Whitelisted links will be displayed normally without redirection.', 'exlink-safe-filter'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e('Greylisted Domains', 'exlink-safe-filter'); ?></th>
                        <td>
                            <textarea name="exlink_settings[greylist]" rows="5" cols="50" class="large-text"><?php echo esc_textarea($settings['greylist']); ?></textarea>
                            <p class="description"><?php esc_html_e('One domain per line. Greylisted links will use the redirect page but automatically proceed.', 'exlink-safe-filter'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e('Blacklisted Domains', 'exlink-safe-filter'); ?></th>
                        <td>
                            <textarea name="exlink_settings[blacklist]" rows="5" cols="50" class="large-text"><?php echo esc_textarea($settings['blacklist']); ?></textarea>
                            <p class="description"><?php esc_html_e('One domain per line. Blacklisted links will be blocked with a warning message.', 'exlink-safe-filter'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <h2 class="title"><?php esc_html_e('Security Settings', 'exlink-safe-filter'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Unknown Domain Action', 'exlink-safe-filter'); ?></th>
                        <td>
                            <label>
                                <input type="radio" name="exlink_settings[unknown_action]" value="redirect_3s" <?php checked($settings['unknown_action'], 'redirect_3s'); ?>>
                                <?php esc_html_e('Show redirect page for 3 seconds then proceed', 'exlink-safe-filter'); ?>
                            </label><br>
                            
                            <label>
                                <input type="radio" name="exlink_settings[unknown_action]" value="show_url" <?php checked($settings['unknown_action'], 'show_url'); ?>>
                                <?php esc_html_e('Show URL without linking (text only)', 'exlink-safe-filter'); ?>
                            </label><br>
                            
                            <label>
                                <input type="radio" name="exlink_settings[unknown_action]" value="show_encoded" <?php checked($settings['unknown_action'], 'show_encoded'); ?>>
                                <?php esc_html_e('Show URL with HTML entities (encoded)', 'exlink-safe-filter'); ?>
                            </label><br>
                            
                            <label>
                                <input type="radio" name="exlink_settings[unknown_action]" value="block" <?php checked($settings['unknown_action'], 'block'); ?>>
                                <?php esc_html_e('Block with warning message (same as blacklist)', 'exlink-safe-filter'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e('URL Encryption', 'exlink-safe-filter'); ?></th>
                        <td>
                            <select name="exlink_settings[encryption]">
                                <option value="none" <?php selected($settings['encryption'], 'none'); ?>><?php esc_html_e('None (plain text)', 'exlink-safe-filter'); ?></option>
                                <option value="base64" <?php selected($settings['encryption'], 'base64'); ?>><?php esc_html_e('Base64 Encoding', 'exlink-safe-filter'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('Applies to redirected URLs only', 'exlink-safe-filter'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e('Warning Message', 'exlink-safe-filter'); ?></th>
                        <td>
                            <input type="text" name="exlink_settings[warning_message]" value="<?php echo esc_attr($settings['warning_message']); ?>" class="regular-text">
                            <p class="description"><?php esc_html_e('Shown for blacklisted and blocked links', 'exlink-safe-filter'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e('Redirect Message', 'exlink-safe-filter'); ?></th>
                        <td>
                            <input type="text" name="exlink_settings[unknown_message]" value="<?php echo esc_attr($settings['unknown_message']); ?>" class="regular-text">
                            <p class="description"><?php esc_html_e('Shown for unknown domain redirects', 'exlink-safe-filter'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <h2 class="title"><?php esc_html_e('Advanced Settings', 'exlink-safe-filter'); ?></h2>
                <table class="form-table">
  
                    <tr>
                        <th scope="row"><?php esc_html_e('Intermediate Page Transcoding', 'exlink-safe-filter'); ?></th>
                        <td>
                            <select name="exlink_settings[intermediate_page_transcode]" id="intermediate_page_transcode">
                                <option value="none" <?php selected($settings['intermediate_page_transcode'], 'none'); ?>><?php esc_html_e('No Transcoding', 'exlink-safe-filter'); ?></option>
                                <option value="mask" <?php selected($settings['intermediate_page_transcode'], 'mask'); ?>><?php esc_html_e('Mask Domain (e.g. ex***le.com)', 'exlink-safe-filter'); ?></option>
                                <option value="entity" <?php selected($settings['intermediate_page_transcode'], 'entity'); ?>><?php esc_html_e('HTML Entity Encoding', 'exlink-safe-filter'); ?></option>
                                <option value="custom" <?php selected($settings['intermediate_page_transcode'], 'custom'); ?>><?php esc_html_e('Replace with Custom Text', 'exlink-safe-filter'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('How to display domain names in intermediate page', 'exlink-safe-filter'); ?></p>
                            <div id="intermediate_transcode_text" style="margin-top:10px; display:<?php echo $settings['intermediate_page_transcode'] === 'custom' ? 'block' : 'none'; ?>">
                                <input type="text" name="exlink_settings[intermediate_transcode_custom_text]" value="<?php echo esc_attr($settings['intermediate_transcode_custom_text']); ?>" class="regular-text">
                                <p class="description"><?php esc_html_e('Custom text to replace domain name in intermediate page', 'exlink-safe-filter'); ?></p>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e('Allow Intermediate Page Indexing', 'exlink-safe-filter'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="exlink_settings[allow_index]" value="1" <?php checked($settings['allow_index'], 1); ?>>
                                <?php esc_html_e('Allow search engines to index intermediate pages', 'exlink-safe-filter'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Uncheck to add noindex meta tag to prevent indexing', 'exlink-safe-filter'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Show Plugin Footer', 'exlink-safe-filter'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="exlink_settings[show_footer]" value="1" <?php checked($settings['show_footer'], 1); ?>>
                                <?php esc_html_e('Display plugin information in intermediate page footer', 'exlink-safe-filter'); ?>
                            </label>
                        </td>
                    </tr>

                </table>

                <h2 class="title"><?php esc_html_e('Custom CSS', 'exlink-safe-filter'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Custom Styles', 'exlink-safe-filter'); ?></th>
                        <td>
                            <textarea name="exlink_settings[custom_css]" rows="5" cols="50" class="large-text code"><?php echo esc_textarea($settings['custom_css']); ?></textarea>
                            <p class="description"><?php esc_html_e('Custom CSS for warning messages and redirect pages', 'exlink-safe-filter'); ?></p>
                        </td>
                    </tr>
                </table>

            <p class="submit">
    <input type="submit" name="reset_defaults" class="button button-secondary" value="<?php esc_html_e('Restore default settings', 'exlink-safe-filter'); ?>" onclick="return confirm('<?php esc_html_e('Are you sure you want to restore all settings to their default values?', 'exlink-safe-filter'); ?>')">
    <input type="submit" name="submit" class="button button-primary" value="<?php esc_html_e('Save Changes', 'exlink-safe-filter'); ?>">
</p>
</form>
        </div>
<?php         
        wp_register_script( 'exlink-admin-js', '', array("jquery"), '1.0', true );
        wp_enqueue_script( 'exlink-admin-js' );
        wp_add_inline_script( 'exlink-admin-js',"
      jQuery(document).ready(function($) {
            // 中转页转码自定义文本控制
            $('#intermediate_page_transcode').change(function() {
                if ($(this).val() === 'custom') {
                    $('#intermediate_transcode_text').show();
                } else {
                    $('#intermediate_transcode_text').hide();
                }
            });
            
            // 域名转码自定义文本控制
            $('#domain_transcode').change(function() {
                if ($(this).val() === 'custom') {
                    $('#domain_transcode_text').show();
                } else {
                    $('#domain_transcode_text').hide();
                }
            });
        });
        "); 
?>
        

        <?php
    }



    // 获取设置
    private function get_settings() {
        $settings = get_option('exlink_settings', []);
        return wp_parse_args($settings, $this->default_settings);
    }

    // 内容过滤（显示时处理）
    public function filter_content($content) {
        $settings = $this->get_settings();
        
        // 检查插件是否启用
        if (!$settings['enabled']) {
            return $content;
        }
        
        // 使用DOMDocument处理HTML内容
        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        // 处理不同类型的元素
        $elements = [];
        
        if (in_array('html_links', $settings['scope_elements'])) {
            $elements = array_merge($elements, iterator_to_array($dom->getElementsByTagName('a')));
        }
        
        if (in_array('images', $settings['scope_elements'])) {
            $elements = array_merge($elements, iterator_to_array($dom->getElementsByTagName('img')));
        }
        
        if (in_array('other', $settings['scope_elements'])) {
            $elements = array_merge($elements, iterator_to_array($dom->getElementsByTagName('iframe')));
            $elements = array_merge($elements, iterator_to_array($dom->getElementsByTagName('script')));
            $elements = array_merge($elements, iterator_to_array($dom->getElementsByTagName('link')));
        }
        
        foreach ($elements as $element) {
            $tag = $element->tagName;
            $url = '';
            
            if ($tag === 'a') {
                $url = $element->getAttribute('href');
            } elseif ($tag === 'img' || $tag === 'script' || $tag === 'iframe') {
                $url = $element->getAttribute('src');
            } elseif ($tag === 'link') {
                $url = $element->getAttribute('href');
            }
            
            if (!empty($url)) {
                $this->process_element($element, $url, $tag);
            }
        }
        
        // 处理纯文本URL和邮件地址
        if (in_array('plaintext', $settings['scope_elements']) || in_array('emails', $settings['scope_elements'])) {
            $xpath = new DOMXPath($dom);
            $textNodes = $xpath->query('//text()');
            
            foreach ($textNodes as $textNode) {
                $text = $textNode->wholeText;
                $processed = $this->process_text($text);
                
                if ($text !== $processed) {
                    $newNode = $dom->createDocumentFragment();
                    $newNode->appendXML($processed);
                    $textNode->parentNode->replaceChild($newNode, $textNode);
                }
            }
        }
        
        // 保存并返回处理后的HTML
        return $dom->saveHTML();
    }

    // 处理文本内容（纯文本URL和邮件）
    private function process_text($text) {
        $settings = $this->get_settings();
        
        // 处理纯文本URL
        if (in_array('plaintext', $settings['scope_elements'])) {
            $pattern = '/(?:(?:https?|ftp):\/\/|www\.)[^\s<>"]+[^\s<>"\.)]/i';
            $text = preg_replace_callback($pattern, function($matches) {
                return $this->create_text_link($matches[0]);
            }, $text);
        }
        
        // 处理邮件地址
        if (in_array('emails', $settings['scope_elements'])) {
            $pattern = '/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i';
            $text = preg_replace_callback($pattern, function($matches) {
                return $this->create_email_link($matches[0]);
            }, $text);
        }
        
        return $text;
    }

    // 创建纯文本URL链接
    private function create_text_link($url) {
        $settings = $this->get_settings();
        $domain = $this->get_domain_from_url($url);
        $list_status = $this->get_domain_status($domain);
        
        if ($list_status === 'whitelist') {
            return '<a href="' . esc_url($url) . '">' . esc_html($url) . '</a>';
        } else {
            $new_url = $this->create_redirect_url($url);
            $transcoded_text = $this->transcode_link_text($url);
            return '<a href="' . esc_url($new_url) . '">' . esc_html($transcoded_text) . '</a>';
        }
    }

    // 创建邮件链接
    private function create_email_link($email) {
        $settings = $this->get_settings();
        $domain = substr(strrchr($email, "@"), 1);
        $list_status = $this->get_domain_status($domain);
        
        if ($list_status === 'whitelist') {
            return '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>';
        } else {
            $new_url = $this->create_redirect_url('mailto:' . $email);
            return '<a href="' . esc_url($new_url) . '">' . esc_html($email) . '</a>';
        }
    }

    // 处理HTML元素
    private function process_element($element, $url, $tag) {
        $settings = $this->get_settings();
        $domain = $this->get_domain_from_url($url);
        
        // 获取域名状态
        $list_status = $this->get_domain_status($domain);
        
        // 处理白名单链接
        if ($list_status === 'whitelist') {
            // 白名单链接不需要修改
            return;
        }
        
        // 处理黑名单链接
        if ($list_status === 'blacklist') {
            if ($tag === 'a') {
                $element->setAttribute('href', '#');
                $element->setAttribute('onclick', 'return false;');
                $element->setAttribute('title', $settings['warning_message']);
                
                // 添加警告样式类
                $classes = $element->getAttribute('class');
                $element->setAttribute('class', trim($classes . ' exlink-blocked'));
                
                // 创建警告元素
                $warning = $element->ownerDocument->createElement('div');
                $warning->setAttribute('class', 'exlink-warning');
                $warning->textContent = $settings['warning_message'];
                
                $element->parentNode->insertBefore($warning, $element);
            } else {
                // 对于非链接元素，移除src属性
                $element->removeAttribute('src');
            }
            
            return;
        }
        
        // 处理灰名单和未知域名
        $new_url = $this->create_redirect_url($url);
        
        if ($tag === 'a') {
            $element->setAttribute('href', $new_url);
        } else {
            $element->setAttribute('src', $new_url);
        }
        
        // 保留原始URL用于审计
        if ($settings['audit_mode']) {
            $element->setAttribute('data-original-url', $url);
        }
    }

    // 创建重定向URL
    private function create_redirect_url($url) {
        $settings = $this->get_settings();
        $slug = $settings['redirect_slug'];
        
        // 加密URL
        switch ($settings['encryption']) {
            case 'base64':
                $encoded = rtrim(strtr(base64_encode($url), '+/', '-_'), '=');
                break;
            default:
                $encoded = urlencode($url);
        }
        
        // 使用固定签名代替nonce令牌以支持缓存
        $salt = wp_salt('exlink_safe_filter');
        $signature = hash_hmac('sha256', $encoded, $salt);
        return home_url($slug . '/?url=' . $encoded . '&sig=' . $signature);
    }

    // 处理重定向
    public function handle_redirect() {
        if (!get_query_var('exlink_redirect')) return;
        
        $settings = $this->get_settings();
        $url_param = isset($_GET['url']) ? sanitize_text_field(wp_unslash($_GET['url'])) : '';
        $original_url = '';
        
        // 解码URL
        if (!empty($url_param)) {
            switch ($settings['encryption']) {
                case 'base64':
                    $original_url = base64_decode(str_pad(strtr($url_param, '-_', '+/'), strlen($url_param) % 4, '=', STR_PAD_RIGHT));
                    break;
                default:
                    $original_url = urldecode($url_param);
            }
        }
        
        // 验证URL
        $original_url = filter_var($original_url, FILTER_SANITIZE_URL);
        if (!filter_var($original_url, FILTER_VALIDATE_URL)) {
            wp_die('Invalid URL');
        }
        
        $domain = $this->get_domain_from_url($original_url);
        $list_status = $this->get_domain_status($domain);
        
        // 处理灰名单 - 直接重定向
        if ($list_status === 'greylist') {
            #wp_redirect(esc_url_raw($original_url));
            $this->show_redirect_page($original_url, $settings['unknown_message']);
            exit;
        }
        
        // 处理未知域名 - 根据设置操作
        if ($list_status === 'unknown') {
            if ($settings['unknown_action'] === 'redirect_3s') {
                $this->show_redirect_page($original_url, $settings['unknown_message']);
            } elseif ($settings['unknown_action'] === 'show_url') {
                $this->show_url_page($original_url, $settings['unknown_message']);
            } elseif ($settings['unknown_action'] === 'show_encoded') {
                $this->show_encoded_url($original_url, $settings['unknown_message']);
            } else {
                $this->show_blocked_page($settings['warning_message']);
            }
            exit;
        }
        
        // 处理黑名单 - 显示阻止页面
        if ($list_status === 'blacklist') {
            $this->show_blocked_page($settings['warning_message']);
            exit;
        }
        
        // 默认重定向（灰名单情况）
        wp_safe_redirect(esc_url_raw($original_url));
        exit;
    }

    // 显示重定向页面（3秒后跳转）
    private function show_redirect_page($url, $message) {
        // 验证签名（固定签名机制）
        // 使用请求中的原始编码URL进行签名验证，确保与生成时一致
        $encoded_url = isset($_GET['url']) ? sanitize_text_field(wp_unslash($_GET['url'])) : '';
        $salt = wp_salt('exlink_safe_filter');
        $expected_signature = hash_hmac('sha256', $encoded_url, $salt);
        if (empty(wp_unslash($_GET['sig'])) || !hash_equals($expected_signature, wp_unslash($_GET['sig']))) {
            wp_die(esc_html_e('链接验证失败，可能已被篡改。', 'exlink-safe-filter'));
        }
        
        $settings = $this->get_settings();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <?php if (!$settings['allow_index']): ?>
            <meta name="robots" content="noindex, nofollow">
            <?php endif; ?>
            <title>Security Redirect</title>
            <meta http-equiv="refresh" content="5;url=<?php echo esc_url($url); ?>">
            <style>
/* Default styles */
.exlink-redirect-container,.exlink-blocked-container{max-width:600px;margin:50px auto;padding:30px;font-family:'Segoe UI',Arial,sans-serif;box-shadow:0 4px 20px rgba(0,0,0,0.1);border-radius:12px;background-color:#ffffff;}
h2{color:#d9534f;margin-top:0;border-bottom:2px solid #f5f5f5;padding-bottom:10px;}
p{line-height:1.6;color:#333333;}
a{color:#0275d8;text-decoration:none;font-weight:500;}
a:hover{text-decoration:underline;}
.exlink-warning{padding:15px;background-color:#f8d7da;color:#721c24;border-radius:6px;margin-bottom:20px;}
.exlink-footer{margin-top:30px;padding-top:20px;border-top:1px solid #eee;text-align:center;font-size:0.9em;color:#666;}
strong{color:#292b2c;}

<?php echo esc_attr(get_option('exlink_custom_css', '')); ?>
</style>
        </head>
        <body>
            <div class="exlink-redirect-container">
                <h2><?php esc_html_e('Security Notice', 'exlink-safe-filter'); ?></h2>
                <p><?php echo esc_html($message); ?></p>
                <p><?php esc_html_e('You are being redirected to:', 'exlink-safe-filter'); ?> <strong><?php echo esc_html($this->mask_url($url)); ?></strong></p>
                <p><?php esc_html_e('Redirecting in 3 seconds...', 'exlink-safe-filter'); ?></p>
                <p><a href="<?php echo esc_url($url); ?>"><?php esc_html_e('Click here if not redirected', 'exlink-safe-filter'); ?></a></p>
            </div>
            <?php if ($settings['show_footer']): ?>
            <div class="exlink-footer">
                <p><?php esc_html_e('Powered by ExLink Safe Filter', 'exlink-safe-filter'); ?> | <a href="https://blog.c1gstudio.com" target="_blank"><?php esc_html_e('Author', 'exlink-safe-filter'); ?></a></p>
            </div>
            <?php endif; ?>
        </body>
        </html>
        <?php
        exit;
    }

    // 显示URL页面（无跳转）
    private function show_url_page($url, $message) {
        $settings = $this->get_settings();
        ?>
            <title>Security Notice</title>
            <style>
/* Default styles */
.exlink-redirect-container,.exlink-blocked-container{max-width:600px;margin:50px auto;padding:30px;font-family:'Segoe UI',Arial,sans-serif;box-shadow:0 4px 20px rgba(0,0,0,0.1);border-radius:12px;background-color:#ffffff;}
h2{color:#d9534f;margin-top:0;border-bottom:2px solid #f5f5f5;padding-bottom:10px;}
p{line-height:1.6;color:#333333;}
a{color:#0275d8;text-decoration:none;font-weight:500;}
a:hover{text-decoration:underline;}
.exlink-warning{padding:15px;background-color:#f8d7da;color:#721c24;border-radius:6px;margin-bottom:20px;}
.exlink-footer{margin-top:30px;padding-top:20px;border-top:1px solid #eee;text-align:center;font-size:0.9em;color:#666;}
strong{color:#292b2c;}

<?php echo esc_attr(get_option('exlink_custom_css', '')); ?>
</style>
        </head>
        <body>
            <div class="exlink-redirect-container">
                <h2><?php esc_html_e('Security Notice', 'exlink-safe-filter'); ?></h2>
                <p><?php echo esc_html($message); ?></p>
                <p>Requested URL: <strong><?php echo esc_html($url); ?></strong></p>
                <p><?php esc_html_e('This link has been disabled for security reasons.', 'exlink-safe-filter'); ?></p>
                <p><a href="<?php echo esc_url(home_url()); ?>"><?php esc_html_e('Return to home page', 'exlink-safe-filter'); ?></a></p>
            </div>
            <?php if ($settings['show_footer']): ?>
            <div class="exlink-footer">
                  <p><?php esc_html_e('Powered by ExLink Safe Filter', 'exlink-safe-filter'); ?> | <a href="https://blog.c1gstudio.com" target="_blank"><?php esc_html_e('Author', 'exlink-safe-filter'); ?></a></p>
            </div>
            <?php endif; ?>            
        </body>
        </html>
        <?php
        exit;
    }

    // 显示编码URL页面
    private function show_encoded_url($url, $message) {
        $settings = $this->get_settings();
        $encoded_url = esc_html($this->transcode_intermediate_domain($url));
        ?>
            <title>Security Notice3</title>
            <style>
/* Default styles */
.exlink-redirect-container, .exlink-blocked-container { max-width: 600px; margin: 50px auto; padding: 30px; font-family: 'Segoe UI', Arial, sans-serif; box-shadow: 0 4px 20px rgba(0,0,0,0.1); border-radius: 12px; background-color: #ffffff; }
h2 { color: #d9534f; margin-top: 0; border-bottom: 2px solid #f5f5f5; padding-bottom: 10px; }
p { line-height: 1.6; color: #333333; }
a { color: #0275d8; text-decoration: none; font-weight: 500; }
a:hover { text-decoration: underline; } 
.exlink-warning { padding: 15px; background-color: #f8d7da; color: #721c24; border-radius: 6px; margin-bottom: 20px; }
.exlink-footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; text-align: center; font-size: 0.9em; color: #666; }

<?php echo esc_attr(get_option('exlink_custom_css', '')); ?>
</style>
        </head>
        <body>
            <div class="exlink-redirect-container">
                <h2><?php esc_html_e('Security Notice', 'exlink-safe-filter'); ?></h2>
                <p><?php echo esc_html($message); ?></p>
                <p>Requested URL: <code><?php echo esc_url($encoded_url); ?></code></p>
                <p><?php esc_html_e('This link has been disabled for security reasons.', 'exlink-safe-filter'); ?></p>
                <p><a href="<?php echo esc_url(home_url()); ?>"><?php esc_html_e('Return to home page', 'exlink-safe-filter'); ?></a></p>
            </div>
            <?php if ($settings['show_footer']): ?>
            <div class="exlink-footer">
                  <p><?php esc_html_e('Powered by ExLink Safe Filter', 'exlink-safe-filter'); ?> | <a href="https://blog.c1gstudio.com" target="_blank"><?php esc_html_e('Author', 'exlink-safe-filter'); ?></a></p>
            </div>
            <?php endif; ?>               
        </body>
        </html>
        <?php
        exit;
    }

    // 显示阻止页面
    private function show_blocked_page($message) {
        $settings = $this->get_settings();
        ?>
            <title>Security Blocked</title>
            <style>
/* Default styles */
.exlink-redirect-container, .exlink-blocked-container { max-width: 600px; margin: 50px auto; padding: 30px; font-family: 'Segoe UI', Arial, sans-serif; box-shadow: 0 4px 20px rgba(0,0,0,0.1); border-radius: 12px; background-color: #ffffff; }
h2 { color: #d9534f; margin-top: 0; border-bottom: 2px solid #f5f5f5; padding-bottom: 10px; }
p { line-height: 1.6; color: #333333; }
a { color: #0275d8; text-decoration: none; font-weight: 500; }
a:hover { text-decoration: underline; }
.exlink-warning { padding: 15px; background-color: #f8d7da; color: #721c24; border-radius: 6px; margin-bottom: 20px; }
.exlink-footer{margin-top:30px;padding-top:20px;border-top:1px solid #eee;text-align:center;font-size:0.9em;color:#666;}
strong { color: #292b2c; }

<?php echo esc_attr(get_option('exlink_custom_css', '')); ?>
</style>
        </head>
        <body>
            <div class="exlink-blocked-container">
                <h2><?php esc_html_e('Security Alert', 'exlink-safe-filter'); ?></h2>
                <div class="exlink-warning"><?php echo esc_html($message); ?></div>
                <p><?php esc_html_e('This link has been identified as potentially harmful and has been blocked.', 'exlink-safe-filter'); ?></p>
                <p><a href="<?php echo esc_url(home_url()); ?>"><?php esc_html_e('Return to home page', 'exlink-safe-filter'); ?></a></p>
            </div>
            <?php if ($settings['show_footer']): ?>
            <div class="exlink-footer">
                  <p><?php esc_html_e('Powered by ExLink Safe Filter', 'exlink-safe-filter'); ?> | <a href="https://blog.c1gstudio.com" target="_blank"><?php esc_html_e('Author', 'exlink-safe-filter'); ?></a></p>
            </div>
            <?php endif; ?>                 
        </body>
        </html>
        <?php
        exit;
    }

    private function convertToHtmlEntities($url) {
        $parsed = wp_parse_url($url);
        if (empty($parsed['host'])) {
            return $url;
        }
        // 转换点号为HTML实体
        $masked_host = preg_replace('/\./', '&#46;', $parsed['host']);
        // 转换连字符为HTML实体
        $masked_host = preg_replace('/-/', '&#45;', $masked_host);
        
        $parsed['host'] = $masked_host;
        
        // 重组URL
        $scheme = isset($parsed['scheme']) ? $parsed['scheme'] . '://' : '';
        $user = isset($parsed['user']) ? $parsed['user'] : '';
        $pass = isset($parsed['pass']) ? ':' . $parsed['pass'] : '';
        $pass = ($user || $pass) ? $pass . '@' : '';
        $host = $parsed['host'];
        $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
        $path = isset($parsed['path']) ? $parsed['path'] : '';
        $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';
        $fragment = isset($parsed['fragment']) ? '#' . $parsed['fragment'] : '';
        
        return $scheme . $user . $pass . $host . $port . $path . $query . $fragment;        
    }
    

    private function transcode_intermediate_domain($domain) {
        $settings = $this->get_settings();
        switch ($settings['intermediate_page_transcode']) {
            case 'mask':
                return $this->mask_domain($domain);
            case 'entity':
                return $this->convertToHtmlEntities($domain);
            case 'custom':
                return !empty($settings['intermediate_transcode_custom_text']) ? $settings['intermediate_transcode_custom_text'] : $domain;
            default:
                return $domain;
        }
    }

    private function transcode_link_text($domain) {
        $settings = $this->get_settings();
        switch ($settings['domain_transcode']) {
            case 'mask':
                return $this->mask_url($domain);
            case 'entity':
                return htmlentities($domain, ENT_QUOTES, 'UTF-8');
            case 'custom':
                return !empty($settings['domain_transcode_custom_text']) ? $settings['domain_transcode_custom_text'] : $domain;
            default:
                return $domain;
        }
    }

    private function get_domain_from_url($url) {
        $host = wp_parse_url ($url, PHP_URL_HOST);
        return $host ? $host : '';
    }

    /**
     * 解析域名，提取子域、域名主体和TLD后缀
     * 
     * @param string $domain 要解析的域名
     * @return array 包含子域、域名主体和TLD后缀的关联数组
     */
    private function parseDomain($domain) {
        // 定义已知的TLD后缀列表（包括一级和二级TLD）
        $tldList = [
            // 一级TLD
            'com', 'net', 'org', 'edu', 'gov', 'mil', 'info', 'biz', 'xyz', 'us',
            'io', 'ai', 'co', 'me', 'tv', 'cc', 'cn', 'uk', 'au', 'ca', 'name',
            'jp', 'nz', 'za', 'sg', 'hk', 'tw', 'de', 'fr', 'ru', 'in','kr',
            
            // 二级TLD
            'com.cn', 'org.cn', 'net.cn', 'gov.cn', 'edu.cn', 'ac.cn', 'mil.cn',
            'co.uk', 'org.uk', 'me.uk', 'gov.uk', 'ac.uk', 'police.uk',
            'com.au', 'net.au', 'org.au', 'edu.au', 'gov.au', 'asn.au',
            'co.jp', 'or.jp', 'go.jp', 'ac.jp', 'ed.jp', 'ne.jp',
            'co.nz', 'org.nz', 'net.nz', 'govt.nz', 'ac.nz',
            'co.za', 'org.za', 'gov.za', 'ac.za', 'net.za',
            'com.sg', 'org.sg', 'edu.sg', 'gov.sg',
            'com.hk', 'org.hk', 'edu.hk', 'gov.hk',
            'com.tw', 'org.tw', 'edu.tw', 'gov.tw'
        ];
        
        // 转换为小写并移除空格
        $domain = strtolower(trim($domain));
        
        // 移除协议部分（如果存在）
        $domain = preg_replace('#^https?://#', '', $domain);
        
        // 移除端口和路径部分（如果存在）
        $domain = preg_replace('/:\d+.*$/', '', $domain);
        $domain = preg_replace('/\/.*$/', '', $domain);
        
        // 分割域名
        $parts = explode('.', $domain);
        
        // 初始化结果
        $result = [
            'subdomain' => '',
            'domain_body' => '',
            'tld' => ''
        ];
        
        // 尝试匹配已知的TLD后缀
        $maxTldLength = 0;
        $matchedTld = '';
        
        // 尝试匹配最长可能的TLD（最多4级）
        for ($i = 1; $i <= min(4, count($parts)); $i++) {
            $candidateTld = implode('.', array_slice($parts, -$i));
            
            if (in_array($candidateTld, $tldList)) {
                if ($i > $maxTldLength) {
                    $maxTldLength = $i;
                    $matchedTld = $candidateTld;
                }
            }
        }
        
        // 处理匹配到的TLD
        if ($matchedTld) {
            $tldParts = explode('.', $matchedTld);
            $tldLength = count($tldParts);
            
            // 设置TLD
            $result['tld'] = $matchedTld;
            
            // 提取域名主体（TLD前的部分）
            $domainBodyIndex = count($parts) - $tldLength - 1;
            
            if ($domainBodyIndex >= 0) {
                $result['domain_body'] = $parts[$domainBodyIndex];
                
                // 提取子域（域名主体前的部分）
                if ($domainBodyIndex > 0) {
                    $result['subdomain'] = implode('.', array_slice($parts, 0, $domainBodyIndex));
                }
            }
        } else {
            // 未匹配到已知TLD的处理
            $partsCount = count($parts);        
            if ($partsCount >= 2) {
                // 取最后一部分作为TLD
                $result['tld'] = $parts[$partsCount - 1];              
                // 取倒数第二部分作为域名主体
                $result['domain_body'] = $parts[$partsCount - 2];          
                // 提取子域
                if ($partsCount > 2) {
                    $result['subdomain'] = implode('.', array_slice($parts, 0, $partsCount - 2));
                }
            } elseif ($partsCount === 1) {
                // 只有一段的情况（如localhost）
                $result['domain_body'] = $parts[0];
            }
        }
        
        return $result;
    }

    /**
     * 对域名进行掩码处理
     * 根据设置对域名进行转码或掩码处理，支持自定义文本替换
     * @param string $domain 原始域名
     * @return string 处理后的域名
     */
    private function mask_domain($domain) {
        $settings = $this->get_settings();
        
        // 自定义文本替换
        if ($settings['domain_transcode'] == 'custom' && !empty($settings['domain_transcode_custom_text'])) {
            return $settings['domain_transcode_custom_text'];
        }
        
        // 掩码处理 - 替换主体域名中间部分，保留首尾字符
        if ($settings['domain_transcode'] == 'mask') {
            $domainresult = $this->parseDomain($domain);
            $domain_body = $domainresult['domain_body'];
            $tld = $domainresult['tld'];
            $subdomain = $domainresult['subdomain'];

            // 检查索引是否有效
            if (isset($domain_body)) {
                $main_part = $domain_body;
                $length = strlen($main_part);
                
                if ($length > 2) {
                    $masked_main = substr($main_part, 0, 1) . str_repeat('*', $length - 2) . substr($main_part, -1);
                    $masked_domain = $masked_main . '.' . $tld;
                    if (!empty($subdomain)) {
                        $masked_domain = $subdomain . '.' . $masked_domain;
                    }
                    return $masked_domain;
                }
            }
        }
        
        // 默认返回原始域名
        return $domain;
    }

    /**
     * 处理完整URL，仅对域名部分进行掩码处理，保留URL其他部分
     * @param string $url 完整URL
     * @return string 域名掩码处理后的完整URL
     */
    private function mask_url($url) {
        $parsed = wp_parse_url($url);
        if (empty($parsed['host'])) {
            return $url;
        }
        
        $masked_host = $this->mask_domain($parsed['host']);
        $parsed['host'] = $masked_host;
        
        // 重组URL
        $scheme = isset($parsed['scheme']) ? $parsed['scheme'] . '://' : '';
        $user = isset($parsed['user']) ? $parsed['user'] : '';
        $pass = isset($parsed['pass']) ? ':' . $parsed['pass'] : '';
        $pass = ($user || $pass) ? $pass . '@' : '';
        $host = $parsed['host'];
        $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
        $path = isset($parsed['path']) ? $parsed['path'] : '';
        $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';
        $fragment = isset($parsed['fragment']) ? '#' . $parsed['fragment'] : '';
        
        return $scheme . $user . $pass . $host . $port . $path . $query . $fragment;
    }

    // 获取域名状态
    /**
     * 将通配符模式转换为正则表达式
     * 
     * @param string $pattern 通配符模式（如 *.github.com）
     * @return string 正则表达式
     */
    private function wildcard_to_regex($pattern) {
        // 转义特殊字符
        $regex = preg_quote($pattern, '/');
        // 将*替换为.*
        $regex = str_replace('\*', '.*', $regex);
        // 添加开始和结束锚点，并忽略大小写
        return '/^' . $regex . '$/i';
    }

    /**
     * 检查域名在白名单、灰名单、黑名单中的状态，支持通配符
     * 
     * @param string $domain 要检查的域名
     * @return string 域名状态（whitelist, greylist, blacklist, unknown）
     */
    private function get_domain_status($domain) {
        $settings = $this->get_settings();
        
        // 获取名单并转换为数组
        $whitelist = !empty($settings['whitelist']) ? explode("\n", $settings['whitelist']) : [];
        $greylist = !empty($settings['greylist']) ? explode("\n", $settings['greylist']) : [];
        $blacklist = !empty($settings['blacklist']) ? explode("\n", $settings['blacklist']) : [];
        
        // 移除空行和前后空格
        $whitelist = array_filter(array_map('trim', $whitelist));
        $greylist = array_filter(array_map('trim', $greylist));
        $blacklist = array_filter(array_map('trim', $blacklist));
        
        // 根据运行模式处理
        if ($settings['operation_mode'] === 'whitelist') {
            // 白名单模式：检查是否在白名单中
            foreach ($whitelist as $pattern) {
                $regex = $this->wildcard_to_regex($pattern);
                if (preg_match($regex, $domain)) {
                    return 'whitelist'; // 白名单域名不处理
                }
            }
            // 不在白名单中，检查灰名单和黑名单
            foreach ($greylist as $pattern) {
                $regex = $this->wildcard_to_regex($pattern);
                if (preg_match($regex, $domain)) {
                    return 'greylist';
                }
            }
            foreach ($blacklist as $pattern) {
                $regex = $this->wildcard_to_regex($pattern);
                if (preg_match($regex, $domain)) {
                    return 'blacklist';
                }
            }
            return 'unknown'; // 不在任何名单中，需要处理
        } else {
            // 黑名单模式：默认不处理，仅处理黑名单和灰名单
            foreach ($blacklist as $pattern) {
                $regex = $this->wildcard_to_regex($pattern);
                if (preg_match($regex, $domain)) {
                    return 'blacklist';
                }
            }
            foreach ($greylist as $pattern) {
                $regex = $this->wildcard_to_regex($pattern);
                if (preg_match($regex, $domain)) {
                    return 'greylist';
                }
            }
            return 'whitelist'; // 默认视为白名单，不处理
        }
    }
}

// 初始化插件
new ExLinkFilter();