=== Worddown ===
Contributors: adamalexandersson
Tags: markdown, export, ai, chatbot, content
Requires at least: 5.0
Tested up to: 6.9
Stable tag: 1.1.3
Requires PHP: 8.1
License: GPL v3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Export WordPress pages and posts to markdown files for AI chatbots with support for custom page builders and multilingual content.

== Description ==

Worddown is a powerful WordPress plugin that enables you to export your pages and posts to markdown files, making them perfect for integration with AI chatbots and other markdown-based systems.

= Key Features =

* Export pages and posts to markdown files
* Support for custom page builders (ACF Flexible Content, Elementor, etc.)
* REST API endpoints for programmatic access
* WP-CLI commands for automation
* Multilingual support
* Background export mode for large sites
* Customizable HTML content filters

= Export Methods =

1. WordPress Admin Dashboard
2. WP-CLI Commands
3. REST API Endpoints

= WP-CLI Support =

Export your content directly from the command line:

`wp worddown export`

For large sites, use background mode:

`wp worddown export --background`

= REST API =

Access export functionality programmatically through REST API endpoints:

* GET /wp-json/worddown/v1/files - List all exported markdown files
* GET /wp-json/worddown/v1/files/{post_id} - Get specific file content
* POST /wp-json/worddown/v1/export - Trigger export

= Custom HTML Content Filters =

Customize your markdown output using WordPress filters:

`add_filter('worddown_custom_html_content', function($content, $post_id, $post_type) {
    if ($post_type === 'page') {
        $content .= '<div>My custom HTML for page ' . $post_id . '</div>';
    }
    return $content;
}, 10, 3);`

= Available Translations =

* English
* Swedish (sv_SE)

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/worddown` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Configure the plugin settings through the 'Worddown' menu item in the WordPress admin panel

== Frequently Asked Questions ==

= Can I use this with my custom page builder? =

Yes! Worddown provides filters that allow you to inject custom HTML content from any page builder before the markdown conversion process.

= Does it support multisite? =

Yes, Worddown works with WordPress multisite installations. You can use the --url parameter with WP-CLI commands to target specific subsites.

= How do I handle large exports? =

For large sites, we recommend using either the background mode via WP-CLI (`wp worddown export --background`) or the REST API with the background parameter enabled.

== Changelog ==

= 1.1.3
* Add before/after export hooks to adapter. The Modularity adapter now uses these hooks for pre/post processing, reducing coupling and keeping the core exporter generic.

= 1.1.2
* Fixes and improvements
* Added testet up to Wordpress 6.9

= 1.1.1
* Fixes and improvements

= 1.1.0
* Implement atomic export using pending directory
* General improvements

= 1.0.0 =
* Initial release

== Development ==

For development instructions and advanced usage, please visit the [plugin repository](https://github.com/adamalexandersson/worddown).

= Build Process =

The plugin uses Vite for asset compilation. Development requirements:

* Node.js 16.0 or higher
* npm 8.0 or higher
