<?php
/*
Plugin Name: Image Attachment Report
Description: Lists all image attachments, their usage, dimensions, and resolution ranking. Includes CSV export.
Version: 1.7
Author: Erik Gripestam
*/

if (!defined('ABSPATH')) {
	exit;
}

// Include necessary files
include_once plugin_dir_path(__FILE__) . 'admin-page.php';
include_once plugin_dir_path(__FILE__) . 'image-query.php';

// Enqueue scripts & styles
function iar_enqueue_assets($hook) {
	//if ($hook !== 'upload.php?page=image-attachment-report') {
	if ($hook !== 'upload.php' && strpos($hook, 'image-attachment-report') === false) {
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
		'image-attachment-report',
		'iar_render_admin_page'
	);
}
add_action('admin_menu', 'iar_add_media_submenu');
