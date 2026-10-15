<?php
if (!defined('ABSPATH')) {
	exit;
}

define('IAR_SCAN_DATA_TRANSIENT', 'iar_scan_data');
define('IAR_SCAN_META_TRANSIENT', 'iar_scan_meta');

/**
 * Find where a single attachment is used, across all public post types plus
 * user meta and term meta. Only ever called from iar_run_full_scan() below,
 * i.e. only while a manual scan is running on the plugin's own admin page.
 *
 * Returns a flat list of hits, each one of:
 *   ['type' => 'post', 'post_id' => int, 'methods' => string[]]
 *   ['type' => 'user', 'user_id' => int, 'methods' => string[]]
 *   ['type' => 'term', 'term_id' => int, 'methods' => string[]]
 */
function iar_find_image_usage($attachment_id, $searchable_post_types) {
	global $wpdb;
	$hits = [];

	$image_url = wp_get_attachment_url($attachment_id);
	$like_url  = '%' . $wpdb->esc_like($image_url) . '%';

	$post_type_placeholders = implode(',', array_fill(0, count($searchable_post_types), '%s'));

	// Featured Images
	$post_parents = get_posts([
		'post_type'   => $searchable_post_types,
		'post_status' => ['publish', 'pending', 'draft', 'future', 'private'], // all but inherit, auto-draft and trash
		'meta_query'  => [['key' => '_thumbnail_id', 'value' => $attachment_id]],
		'fields'      => 'ids',
	]);
	foreach ($post_parents as $post_id) {
		$hits[$post_id][] = 'Featured Image';
	}

	// Content URL match (Full URL)
	$content_used_in = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT ID FROM $wpdb->posts
			WHERE post_type IN ($post_type_placeholders)
			AND post_content LIKE %s
			AND post_status NOT IN ('inherit', 'trash', 'auto-draft')",
			array_merge($searchable_post_types, [$like_url])
		)
	);
	foreach ($content_used_in as $post) {
		$hits[$post->ID][] = 'Full URL';
	}

	// Attachment ID referenced in content (ACF galleries stored inline, etc.)
	$id_in_content_used_in = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT ID FROM $wpdb->posts
			WHERE post_type IN ($post_type_placeholders)
			AND post_content REGEXP %s
			AND post_status NOT IN ('inherit', 'trash', 'auto-draft')",
			array_merge($searchable_post_types, ['\\b' . $attachment_id . '\\b'])
		)
	);
	foreach ($id_in_content_used_in as $post) {
		$hits[$post->ID][] = 'In content';
	}

	// Gutenberg Block attribute match (e.g. "id":123)
	$id_used_in = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT ID FROM $wpdb->posts
			WHERE post_type IN ($post_type_placeholders)
			AND post_content LIKE %s
			AND post_status NOT IN ('inherit', 'trash', 'auto-draft')",
			array_merge($searchable_post_types, ['%"id":' . $attachment_id . '%'])
		)
	);
	foreach ($id_used_in as $post) {
		$hits[$post->ID][] = 'In block';
	}

	// Post Meta Search (ACF, WooCommerce, Custom Fields) — not restricted to $searchable_post_types,
	// matching the historical behaviour of this report (a field can live on any post type).
	$post_meta_used_in = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT post_id, meta_key FROM $wpdb->postmeta WHERE meta_value LIKE %s",
			$like_url
		)
	);
	foreach ($post_meta_used_in as $meta) {
		$hits[$meta->post_id][] = "Post Meta (Key: {$meta->meta_key})";
	}

	// User Meta Search (Profile Pictures, ACF Fields)
	$user_meta_used_in = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT user_id, meta_key FROM $wpdb->usermeta WHERE meta_value LIKE %s",
			$like_url
		)
	);
	foreach ($user_meta_used_in as $meta) {
		$hits['user_' . $meta->user_id][] = "User Meta (Key: {$meta->meta_key})";
	}

	// Term Meta Search
	$term_meta_used_in = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT term_id, meta_key FROM $wpdb->termmeta WHERE meta_value LIKE %s",
			$like_url
		)
	);
	foreach ($term_meta_used_in as $meta) {
		$hits['term_' . $meta->term_id][] = "Term Meta (Key: {$meta->meta_key})";
	}

	$results = [];
	foreach ($hits as $id => $methods) {
		$methods = array_values(array_unique($methods));
		if (strpos((string) $id, 'user_') === 0) {
			$results[] = ['type' => 'user', 'user_id' => (int) substr($id, 5), 'methods' => $methods];
		} elseif (strpos((string) $id, 'term_') === 0) {
			$results[] = ['type' => 'term', 'term_id' => (int) substr($id, 5), 'methods' => $methods];
		} else {
			$results[] = ['type' => 'post', 'post_id' => (int) $id, 'methods' => $methods];
		}
	}

	return $results;
}

/**
 * Runs a full scan of every image attachment and caches the result in a transient.
 * This is the only place the expensive per-image usage lookups happen, and it only
 * runs when explicitly triggered (the "Scan" button) from the plugin's own admin page.
 */
function iar_run_full_scan() {
	$excluded_types        = ['attachment'];
	$searchable_post_types = array_diff(get_post_types(['public' => true]), $excluded_types);
	$searchable_post_types[] = 'wp_block';

	$attachments = get_posts([
		'post_type'      => 'attachment',
		'post_status'    => 'inherit',
		'post_mime_type' => 'image',
		'numberposts'    => -1,
		'fields'         => 'ids',
	]);

	$data = [];

	foreach ($attachments as $attachment_id) {
		$meta   = wp_get_attachment_metadata($attachment_id);
		$width  = !empty($meta['width']) ? (int) $meta['width'] : 0;
		$height = !empty($meta['height']) ? (int) $meta['height'] : 0;

		$area_mp = round(($width * $height) / 1000000, 1);
		if ($area_mp < 1) {
			$quality = 'low';
		} elseif ($area_mp < 2) {
			$quality = 'medium';
		} else {
			$quality = 'good';
		}

		$data[$attachment_id] = [
			'width'    => $width,
			'height'   => $height,
			'area_mp'  => $area_mp,
			'quality'  => $quality,
			'filename' => basename(get_attached_file($attachment_id)),
			'used_in'  => iar_find_image_usage($attachment_id, $searchable_post_types),
		];
	}

	set_transient(IAR_SCAN_DATA_TRANSIENT, $data, 0);
	set_transient(IAR_SCAN_META_TRANSIENT, [
		'timestamp' => time(),
		'total'     => count($data),
	], 0);

	return $data;
}

function iar_get_scan_data() {
	$data = get_transient(IAR_SCAN_DATA_TRANSIENT);
	return is_array($data) ? $data : null;
}

function iar_get_scan_meta() {
	$meta = get_transient(IAR_SCAN_META_TRANSIENT);
	return is_array($meta) ? $meta : null;
}
