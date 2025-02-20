<?php
if (!defined('ABSPATH')) {
	exit;
}

// Function to find where an image is used
function iar_find_image_usage($attachment_id, $selected_types) {
	global $wpdb;
	$used_in = [];

	// Get Image URL
	$image_url = esc_sql(wp_get_attachment_url($attachment_id));
	error_log("Image URL: {$image_url}");

	// 🎯 Featured Images
	$post_parents = get_posts([
		'post_type'  => $selected_types,
		'post_status' => array('publish', 'pending', 'draft', 'future', 'private'), //all but inherit, auto-draft and trash
		'meta_query' => [['key' => '_thumbnail_id', 'value' => $attachment_id]],
		'fields'     => 'ids',
	]);
	foreach ($post_parents as $post_id) {
		$used_in[$post_id][] = "Featured Image";
	}

	// 🎯 Content URL match (Full URL)
	$content_used_in = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT ID FROM $wpdb->posts 
          	WHERE post_type IN ('" . implode("','", array_map('esc_sql', $selected_types)) . "') 
			AND post_content LIKE %s
			AND post_status NOT IN ('inherit', 'trash', 'auto-draft')",
			"%" . esc_sql($image_url) . "%"
		)
	);
	foreach ($content_used_in as $post) {
		$used_in[$post->ID][] = "Full URL";
	}

	// 🎯 ACF Gallery Field (product-gallery) match
	// We'll look for any gallery field that contains the image ID
	/*$acf_gallery_used_in = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT post_id, meta_key, meta_value FROM $wpdb->postmeta 
				WHERE meta_key LIKE %s 
			   	AND meta_value LIKE %s",
			'%_gallery%', "%" . esc_sql($attachment_id) . "%" // Matching gallery ID
		)
	);
	foreach ($acf_gallery_used_in as $meta) {
		$used_in[$meta->post_id][] = "ACF Gallery (Key: {$meta->meta_key})";
	}*/

	// 🎯 ACF Gallery Field (product-gallery) match in post_content
	// We'll look for any gallery field that contains the image ID
	$acf_gallery_used_in_content = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT ID FROM $wpdb->posts 
          	WHERE post_type IN ('" . implode("','", array_map('esc_sql', $selected_types)) . "') 
          	AND post_content REGEXP %s
    		AND post_status NOT IN ('inherit', 'trash', 'auto-draft')",
			"\\b" . esc_sql($attachment_id) . "\\b" // Use word boundaries to match exact IDs
		)
	);

	foreach ($acf_gallery_used_in_content as $post) {
		$used_in[$post->ID][] = "In content";
	}

	// 🎯 Gutenberg Block ID match
	$id_used_in = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT ID FROM $wpdb->posts 
          	WHERE post_type IN ('" . implode("','", array_map('esc_sql', $selected_types)) . "') 
          	AND post_content LIKE %s
          	AND post_status NOT IN ('inherit', 'trash', 'auto-draft')",
			"%\"id\":{$attachment_id}%"
		)
	);
	foreach ($id_used_in as $post) {
		$used_in[$post->ID][] = "In block";
	}

	// 🎯 Post Meta Search (ACF, WooCommerce, Custom Fields)
	$post_meta_used_in = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT post_id, meta_key, meta_value FROM $wpdb->postmeta 
				 WHERE meta_value LIKE %s",
			"%{$image_url}%"
		)
	);
	foreach ($post_meta_used_in as $meta) {
		$used_in[$meta->post_id][] = "Post Meta (Key: {$meta->meta_key})";
	}


	// 🎯 User Meta Search (Profile Pictures, ACF Fields)
	$user_meta_used_in = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT user_id, meta_key, meta_value FROM $wpdb->usermeta WHERE meta_value LIKE %s",
			"%{$image_url}%"
		)
	);
	foreach ($user_meta_used_in as $meta) {
		$user = get_userdata($meta->user_id);
		$user_name = $user ? $user->display_name : "Unknown User";
		$used_in["user_{$meta->user_id}"][] = "User Meta (Key: {$meta->meta_key}, User: {$user_name})";
	}

	// 🎯 Term Meta Search
	$term_meta_used_in = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT term_id, meta_key, meta_value FROM $wpdb->termmeta WHERE meta_value LIKE %s",
			"%{$image_url}%"
		)
	);
	foreach ($term_meta_used_in as $meta) {
		$term = get_term($meta->term_id);
		$term_name = $term ? $term->name : "Unknown Term";
		$used_in["term_{$meta->term_id}"][] = "Term Meta (Key: {$meta->meta_key}, Term: {$term_name})";
	}

	return $used_in;
}
