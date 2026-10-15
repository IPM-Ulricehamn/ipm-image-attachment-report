<?php
/*
Plugin Name: IPM Image Attachment Report
Description: Lists all image attachments, their usage, dimensions, and resolution ranking. Includes CSV export.
Version: 1.8.1
Author: Erik Gripestam
Text Domain: ipm-image-attachment-report
Domain Path: /languages
Update URI: https://github.com/IPM-Ulricehamn/ipm-image-attachment-report
GitHub Plugin URI: IPM-Ulricehamn/ipm-image-attachment-report
*/

if (!defined('ABSPATH')) {
	exit;
}

function iar_load_textdomain() {
	load_plugin_textdomain('ipm-image-attachment-report', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('init', 'iar_load_textdomain');

/* Enable automatic updates from this plugin's own GitHub repo if a token is defined in wp-config.php */
add_filter('http_request_args', function($args, $url) {
	$is_this_plugins_update_check = strpos($url, 'github.com/IPM-Ulricehamn/ipm-image-attachment-report') !== false
		|| strpos($url, 'api.github.com/repos/IPM-Ulricehamn/ipm-image-attachment-report') !== false;
	if ($is_this_plugins_update_check && defined('GITHUB_ACCESS_TOKEN')) {
		$args['headers']['Authorization'] = 'Bearer ' . GITHUB_ACCESS_TOKEN;
	}
	return $args;
}, 10, 2);

// Include necessary files
include_once plugin_dir_path(__FILE__) . 'admin-page.php';
include_once plugin_dir_path(__FILE__) . 'image-query.php';

// Enqueue scripts & styles
function iar_enqueue_assets($hook) {
	if ($hook !== 'upload.php' && strpos($hook, 'ipm-image-attachment-report') === false) {
		return;
	}
	$style_path  = plugin_dir_path(__FILE__) . 'assets/style.css';
	$script_path = plugin_dir_path(__FILE__) . 'assets/script.js';

	wp_enqueue_style('iar-style', plugin_dir_url(__FILE__) . 'assets/style.css', [], file_exists($style_path) ? filemtime($style_path) : false);
	wp_enqueue_script('iar-script', plugin_dir_url(__FILE__) . 'assets/script.js', ['jquery'], file_exists($script_path) ? filemtime($script_path) : false, true);
}
add_action('admin_enqueue_scripts', 'iar_enqueue_assets');



// Register submenu under Media
function iar_add_media_submenu() {
	add_submenu_page(
		'upload.php',
		__('Image report', 'ipm-image-attachment-report'),
		__('Image report', 'ipm-image-attachment-report'),
		'manage_options',
		'ipm-image-attachment-report',
		'iar_render_admin_page'
	);
}
add_action('admin_menu', 'iar_add_media_submenu');
