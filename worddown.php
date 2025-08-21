<?php
/**
 * Plugin Name: Worddown
 * Plugin URI: 
 * Description: WordPress plugin that exports pages or posts to a markdown files for AI chatbots
 * Version: 1.1.0
 * Author: Adam Alexandersson
 * Author URI: 
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: worddown
 * Domain Path: /resources/languages
 */

use Worddown\Application\App;

// Check if the plugin is loaded correctly
if (!defined('ABSPATH')) {
    exit;
}

// Check if the plugin is loaded correctly
if (!function_exists('add_action')) {
    exit;
}

// Load the autoloader
$autoloader = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloader)) {
    require_once $autoloader;
} else {
    wp_die('Please run composer install to install the necessary dependencies.');
}

// Load the textdomain
add_action('init', function() {
    load_plugin_textdomain(
        'worddown',
        false,
        dirname(plugin_basename(__FILE__)) . '/resources/languages'
    );

    wp_set_script_translations('worddown', 'worddown');
});

// Boot the plugin
App::boot(); 