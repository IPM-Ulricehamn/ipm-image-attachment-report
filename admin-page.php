<?php
if (!defined('ABSPATH')) {
	exit;
}

function iar_render_admin_page() {
	if (!current_user_can('manage_options')) {
		wp_die('You do not have permission to view this page.');
	}

	//global $wpdb;

	$lowres_threshold = isset($_GET['lowres_threshold']) ? intval($_GET['lowres_threshold']) : 1600;
	$lowres_filter = isset($_GET['lowres']);

	$excluded_types = ['attachment'];
	$post_types = array_diff(get_post_types(['public' => true]), $excluded_types);
	$post_types[] = 'wp_block';

	$selected_types = !empty($_GET['post_types']) && is_array($_GET['post_types'])
		? array_intersect($_GET['post_types'], $post_types)
		: $post_types;

	if (empty($selected_types)) {
		$selected_types = $post_types;
	}

	echo "<div class='wrap'><h1>Image Attachment Report</h1>";
	echo '<div class="export-panel">';
	echo "<button class='button' id='iar-export-csv'>Export current list to CSV</button>";
	echo '</div>';

	// Filters
	echo "<div class='filters-wrap'><form method='get' id='filter-form'>";
	echo "<input type='hidden' name='page' value='ipm-image-attachment-report'>";

	echo "<div><p><strong>Search in post types:</strong></p>";
	foreach ($post_types as $type) {
		$checked = in_array($type, $selected_types) ? "checked" : "";
		if ( $type == 'wp_block' ) {
			echo "<label><input type='checkbox' name='post_types[]' value='{$type}' {$checked}>Pattern</label>";
		} else {
			echo "<label>
					<input type='checkbox' name='post_types[]' value='{$type}' {$checked}> " . ucfirst($type) . "
				  </label>";
		}
	}


	echo "</div><div><p><strong>Filter by resolution:</strong></p><p><label>
            <input type='checkbox' name='lowres' value='1' " . ($lowres_filter ? "checked" : "") . "> Show only low-resolution images
          </label></p>";

	echo "<p><label>Threshold: 
            <input type='number' name='lowres_threshold' placeholder='1600' value='{$lowres_threshold}' min='1' style='width: 80px;'>
          px (width)</label></p></div>";

	echo "</form></div>";



	echo "<div class='toggle-controls'><label>
        <input type='checkbox' id='toggle-thumbnails' checked> Show Thumbnails
      </label><label>
		<input type='checkbox' id='toggle-row-numbers' checked> Show row numbers
	  </label></div>";



	// Fetch all image attachments
	$attachments = get_posts([
		'post_type'      => 'attachment',
		'post_status'    => 'inherit',
		'post_mime_type' => 'image',
		'numberposts'    => -1,
	]);

	if (empty($attachments)) {
		echo "<p>No images found matching the criteria.</p>";
		return;
	}

	// Initialize counters
	$total_images = 0;
	$high_res_images = 0;
	$low_res_images = 0;
	$row_number = 1;

	ob_start();

	foreach ($attachments as $attachment) {
		$meta = wp_get_attachment_metadata($attachment->ID);
		if (!$meta || empty($meta['width'])) {
			$meta = ['width' => 0, 'height' => 0];
		}

		$width = $meta['width'];
		$height = $meta['height'];
		$filename = basename(get_attached_file($attachment->ID));

		$is_low_res = ($width < $lowres_threshold);
		$resolution_status = $is_low_res
			? "<span class='low-res'>Low</span>"
			: "<span class='high-res'>OK</span>";

		if ($is_low_res) {
			$low_res_images++;
		} else {
			$high_res_images++;
		}

		if ($lowres_filter && !$is_low_res) {
			continue;
		}

		$total_images++;

		// Link to the attachment page
		$attachment_link = get_edit_post_link($attachment->ID);

		// Find occurrences and link them
		$used_in = iar_find_image_usage($attachment->ID, $selected_types);
		$used_in_links = '';

		if (!empty($used_in) && is_array($used_in)) {
			foreach ($used_in as $id => $methods) {
				// Check if the ID is user-based or term-based
				if (strpos($id, 'user_') === 0) {
					// Format User Meta results
					$user_id = str_replace("user_", "", $id);
					$user = get_userdata($user_id);
					$used_in_links .= "User: {$user->display_name} (" . implode(", ", array_unique($methods)) . ")<br>";
				} elseif (strpos($id, 'term_') === 0) {
					// Format Term Meta results
					$term_id = str_replace("term_", "", $id);
					$term = get_term($term_id);
					$used_in_links .= "Term: {$term->name} (" . implode(", ", array_unique($methods)) . ")<br>";
				} else {
					// Format Post Meta and Posts
					$post_type = get_post_type($id);
					$post_title = get_the_title($id);
					$edit_link = get_edit_post_link($id);
					$used_in_links .= "<a href='{$edit_link}'>{$post_title}</a>[" . $post_type . "] (" . implode(", ", array_unique($methods)) . ")<br>";
				}
			}
		} else {
			$used_in_links = "Not found in content";
		}


		$thumbnail = wp_get_attachment_image($attachment->ID, 'thumbnail', false, ['class' => 'thumb-preview']);

		echo "<tr>
			<td class='row-number'>{$row_number}</td>
            <td class='toggle-thumbnail'>{$thumbnail}</td>
            <td><a href='{$attachment_link}'>{$filename}</a></td>
            <td>{$used_in_links}</td>
            <td>{$width} x {$height}</td>
            <td>{$resolution_status}</td>
          </tr>";

		$row_number++;
	}

	$table_content = ob_get_clean();

	// Display the counter
	echo "<div class='image-report-stats'>
        <div><strong>Total images (current view):</strong> <span id='total-count'>{$total_images}</span></div>
        <div><strong>High resolution total:</strong> <span id='highres-count'>{$high_res_images}</span></div>
        <div><strong>Low resolution total:</strong> <span id='lowres-count'>{$low_res_images}</span></div>
      </div>";

	if ($total_images > 0) {
		echo "<table class='wp-list-table widefat striped' id='image-report-table'>
            <thead>
            	<tr>
					<th class='row-number'>#</th>
					<th class='toggle-thumbnail'>Thumbnail</th>
					<th>Filename</th>
					<th>Used In</th>
					<th>Dimensions</th>
					<th>Resolution</th>
				</tr>
            </thead>
            {$table_content}
        </table>";
	} else {
		echo "<p>No images found matching the selected filters.</p>";
	}

	echo "</div>"; // Closing wrapper
}
