<?php
/*
Plugin Name: IPM Image Attachment Report
Description: Lists all image attachments, their usage, dimensions, and resolution ranking. Includes CSV export.
Version: 1.7.1
Author: Erik Gripestam
Update URI: https://github.com/IPM-Ulricehamn/ipm-image-attachment-report
GitHub Plugin URI: IPM-Ulricehamn/ipm-image-attachment-report
*/

if (!defined('ABSPATH')) {
	exit;
}

/* Enable automatic updates from github if token is defined in wp-config.php */
add_filter('http_request_args', function($args, $url) {
	if (strpos($url, 'github.com') !== false && defined('GITHUB_ACCESS_TOKEN')) {
		$args['headers']['Authorization'] = 'Bearer ' . GITHUB_ACCESS_TOKEN;
	}
	return $args;
}, 10, 2);

// Include necessary files
include_once plugin_dir_path(__FILE__) . 'admin-page.php';
include_once plugin_dir_path(__FILE__) . 'image-query.php';

// Enqueue scripts & styles
function iar_enqueue_assets($hook) {
	//if ($hook !== 'upload.php?page=ipm-image-attachment-report') {
	if ($hook !== 'upload.php' && strpos($hook, 'ipm-image-attachment-report') === false) {
		return;
	}
	wp_enqueue_style('iar-style', plugin_dir_url(__FILE__) . 'assets/style.css');
	wp_enqueue_script('iar-script', plugin_dir_url(__FILE__) . 'assets/script.js', ['jquery'], null, true);
}
add_action('admin_enqueue_scripts', 'iar_enqueue_assets');



// Register submenu under Media
function iar_add_media_submenu() {
	add_submenu_page(
		'upload.php',
		'Image report',
		'Image report',
		'manage_options',
		'ipm-image-attachment-report',
		'iar_render_admin_page'
	);
}
add_action('admin_menu', 'iar_add_media_submenu');
