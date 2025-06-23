=== exlink-safe-filter - External Link Security ===
Plugin Name: exlink-safe-filter
Contributors: C1G
Donate link: https://blog.c1gstudio.com/
Tags: external links, security, link filtering, whitelist, blacklist
Requires at least: 5.0
Tested up to: 6.8
Stable tag: 2.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Advanced external link security filter plugin with whitelist, greylist, blacklist management and multiple security options.

== Description ==

exlink-safe-filter is an enterprise-grade external link security management plugin designed for WordPress websites. It effectively protects your site users from malicious links while providing a good user experience through advanced link classification system and flexible security policies.

Key Features:

* **Multi-level Link Classification System**
  - Whitelist: Fully trusted domains, display original links directly
  - Greylist: Go through intermediate page but auto-redirect
  - Blacklist: Fully block access and display warning message
  - Unknown domains: Configurable handling methods (3-second auto-redirect, display URL, display encoded URL, or block access)

* **Comprehensive Content Processing Scope**
  - Content types: Posts, pages, comments, products (WooCommerce supported)
  - Element types: HTML links (a tags), plain text URLs, email addresses, image resources, other resources (scripts, iframes, etc.)

* **Flexible Processing Options**
  - Processing time: Process when displaying (recommended) or when publishing/editing
  - Audit mode: Preserve original URL in data-original-url attribute for auditing
  - URL encryption: Support Base64, ROT13 encryption or plain text display
  - Custom redirect slug: Defaults to /exlink-safe-redirect/

* **Advanced Security Features**
  - Domain masking: Replace middle part of main domain while preserving first and last characters (e.g., display www.domain.com.cn as www.d***n.com.cn)
  - Comprehensive domain verification system
  - Custom warning and security messages
  - Multi-language support: Chinese (Simplified) and English
  - Custom CSS styling: Customize intermediate page and warning message styles

* **User-friendly Interface**
  - Intuitive settings page
  - Detailed option descriptions
  - Responsive design,适配各种设备

== Installation ==

1. Download and install the plugin from the WordPress plugin directory, or upload the `exlink-safe-filter` folder to the `/wp-content/plugins/` directory
2. Activate the plugin in the WordPress admin panel
3. Navigate to 【Settings】→【exlink-safe-filter Security】 to configure plugin options
4. Set up whitelist, greylist and blacklist according to your needs
5. Configure security policies and display options
6. The plugin will take effect automatically after saving settings

== Screenshots ==

1. Security intermediate page example 
2. Display page - Domain list
3. Settings page - General options

== Frequently Asked Questions ==

= How to add domains to the whitelist? =
In the "Domain Lists" tab of the settings page, add domains to the whitelist text box, one domain per line(*.example.com).

= How to customize the intermediate page style? =
In the "Custom CSS" tab, add your custom CSS code, which will be automatically applied to the intermediate page and warning messages.

= What languages does the plugin support? =
Currently supports Chinese (Simplified) and English, which can be switched in "General Settings" of the settings page.

= How to configure the domain masking feature? =
In "Security Settings", set "Domain Transcode Method" to "Mask", and the system will automatically replace the middle characters of the main domain part with asterisks.

== Changelog ==

= 2.0.1 =
* Added restore defaults feature (with confirmation dialog)
* Improved settings page layout and prompt copy
* Optimized default installation configurations
  - Disabled by default
  - Content scope defaults to posts only
  - Element scope defaults to HTML links only

= 2.0 =
* Added domain masking feature to replace middle part of main domain
* Added multi-language support with Chinese and English
* Optimized CSS styles using single-line CSS elements
* Fixed language switching function bug
* Improved intermediate page URL display logic

= 1.0 =
* Initial version release
* Basic link classification functionality
* Whitelist, greylist, blacklist management
* Custom intermediate page

== Additional Information ==

* Plugin Development: C1G Studio
* Author Website: https://blog.c1gstudio.com
* Support Email: service@c1gstudio.com
* License: GPLv2 or later