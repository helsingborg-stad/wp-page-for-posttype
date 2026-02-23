<?php

/**
 * Plugin Name:       WP Page for post type
 * Plugin URI:        https://github.com/helsingborg-stad
 * Description:       Set a page where the post type's archive should be displayed
 * Version: 2.0.6
 * Author:            Kristoffer Svanmark, Sebastian Thulin
 * Author URI:        https://github.com/helsingborg-stad
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       wp-page-for-post-type
 * Domain Path:       /languages
 */

 // Protect agains direct file access
if (! defined('WPINC')) {
    die;
}

define('WP_PAGE_FOR_POST_TYPE_PATH', plugin_dir_path(__FILE__));
define('WP_PAGE_FOR_POST_TYPE_URL', plugins_url('', __FILE__));
define('WP_PAGE_FOR_POST_TYPE_TEMPLATE_PATH', WP_PAGE_FOR_POST_TYPE_PATH . 'templates/');

load_plugin_textdomain('wp-page-for-post-type', false, plugin_basename(dirname(__FILE__)) . '/languages');

// Autoload from plugin
if (file_exists(WP_PAGE_FOR_POST_TYPE_PATH . 'vendor/autoload.php')) {
    require_once WP_PAGE_FOR_POST_TYPE_PATH . 'vendor/autoload.php';
}
require_once WP_PAGE_FOR_POST_TYPE_PATH . 'Public.php';

// Start application
new WpPageForPostType\App();
