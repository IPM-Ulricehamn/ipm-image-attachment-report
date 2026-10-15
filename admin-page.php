<?php
if (!defined('ABSPATH')) {
	exit;
}

define('IAR_PER_PAGE', 50);

function iar_render_admin_page() {
	if (!current_user_can('manage_options')) {
		wp_die(esc_html__('You do not have permission to view this page.', 'ipm-image-attachment-report'));
	}

	if (isset($_POST['iar_scan']) && check_admin_referer('iar_scan_action', 'iar_scan_nonce')) {
		iar_run_full_scan();
		echo '<div class="notice notice-success is-dismissible"><p>' .
			esc_html__('Scan complete.', 'ipm-image-attachment-report') .
			'</p></div>';
	}

	$scan_data = iar_get_scan_data();
	$scan_meta = iar_get_scan_meta();

	$excluded_types = ['attachment'];
	$post_types = array_diff(get_post_types(['public' => true]), $excluded_types);
	$post_types[] = 'wp_block';

	// Post types are opt-out: nothing is excluded unless ticked, so an empty selection needs no fallback.
	$excluded_post_types = !empty($_GET['exclude_post_types']) && is_array($_GET['exclude_post_types'])
		? array_values(array_intersect(array_map('sanitize_key', $_GET['exclude_post_types']), $post_types))
		: [];
	$selected_types = array_values(array_diff($post_types, $excluded_post_types));

	$lowres_filter = isset($_GET['lowres']);

	// File types present in the cached scan (e.g. jpg, png, svg), offered as exclusion options.
	$available_file_types = is_array($scan_data) ? iar_get_available_file_types($scan_data) : [];
	$excluded_file_types  = !empty($_GET['exclude_types']) && is_array($_GET['exclude_types'])
		? array_values(array_intersect(array_map('sanitize_key', $_GET['exclude_types']), $available_file_types))
		: [];

	echo "<div class='wrap'><h1>" . esc_html__('Image Attachment Report', 'ipm-image-attachment-report') . '</h1>';

	iar_render_scan_panel($scan_meta);

	if (!is_array($scan_data)) {
		echo '<p>' . esc_html__('No report has been generated yet. Click "Run scan" above to build one.', 'ipm-image-attachment-report') . '</p></div>';
		return;
	}

	echo '<div class="export-panel">';
	echo "<button class='button' id='iar-export-csv'>" . esc_html__('Export current list to CSV', 'ipm-image-attachment-report') . '</button>';
	echo '</div>';

	iar_render_filters($post_types, $excluded_post_types, $lowres_filter, $available_file_types, $excluded_file_types);

	echo '<hr />';

	echo "<div class='toggle-controls'><label>
			<input type='checkbox' id='toggle-thumbnails' checked> " . esc_html__('Show Thumbnails', 'ipm-image-attachment-report') . "
		  </label><label>
			<input type='checkbox' id='toggle-row-numbers' checked> " . esc_html__('Show row numbers', 'ipm-image-attachment-report') . '
		  </label></div>';

	// Quality counters always reflect the full scanned library, not the current filter.
	$good_images = 0;
	$medium_images = 0;
	$low_images = 0;
	foreach ($scan_data as $record) {
		if ($record['quality'] === 'good') {
			$good_images++;
		} elseif ($record['quality'] === 'medium') {
			$medium_images++;
		} else {
			$low_images++;
		}
	}

	// Apply the low-res filter against the cached scan (cheap, in-memory — no DB queries).
	$filtered_ids = [];
	foreach ($scan_data as $attachment_id => $record) {
		if ($lowres_filter && $record['quality'] !== 'low') {
			continue;
		}
		if ($excluded_file_types && in_array(iar_get_file_type($record['filename']), $excluded_file_types, true)) {
			continue;
		}
		$filtered_ids[] = $attachment_id;
	}

	$total_images = count($filtered_ids);

	echo "<div class='image-report-stats'>
			<div><strong>" . esc_html__('Total images (current view):', 'ipm-image-attachment-report') . "</strong> <span id='total-count'>" . esc_html($total_images) . "</span></div>
			<div><strong>" . esc_html__('Good (≥ 2 MP):', 'ipm-image-attachment-report') . "</strong> <span id='good-count'>" . esc_html($good_images) . "</span></div>
			<div><strong>" . esc_html__('Medium (1-2 MP):', 'ipm-image-attachment-report') . "</strong> <span id='medium-count'>" . esc_html($medium_images) . "</span></div>
			<div><strong>" . esc_html__('Low (< 1 MP):', 'ipm-image-attachment-report') . "</strong> <span id='low-count'>" . esc_html($low_images) . "</span></div>
		  </div>";

	if ($total_images === 0) {
		echo '<p>' . esc_html__('No images found matching the selected filters.', 'ipm-image-attachment-report') . '</p></div>';
		return;
	}

	$per_page    = IAR_PER_PAGE;
	$total_pages = (int) ceil($total_images / $per_page);
	$current_page = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
	$current_page = min($current_page, $total_pages);
	$offset = ($current_page - 1) * $per_page;
	$page_ids = array_slice($filtered_ids, $offset, $per_page);

	$row_number = $offset + 1;

	echo "<table class='wp-list-table widefat striped' id='image-report-table'>
			<thead>
				<tr>
					<th class='row-number'>#</th>
					<th class='toggle-thumbnail'>" . esc_html__('Thumbnail', 'ipm-image-attachment-report') . "</th>
					<th>" . esc_html__('Filename', 'ipm-image-attachment-report') . "</th>
					<th>" . esc_html__('Used In', 'ipm-image-attachment-report') . "</th>
					<th>" . esc_html__('Dimensions', 'ipm-image-attachment-report') . "</th>
					<th>" . esc_html__('Resolution', 'ipm-image-attachment-report') . "</th>
				</tr>
			</thead>";

	foreach ($page_ids as $attachment_id) {
		iar_render_row($attachment_id, $scan_data[$attachment_id], $selected_types, $row_number);
		$row_number++;
	}

	echo '</table>';

	iar_render_pagination($current_page, $total_pages);

	echo '</div>'; // Closing wrapper
}

function iar_render_scan_panel($scan_meta) {
	echo '<div class="scan-panel">';
	if ($scan_meta) {
		$when = sprintf(
			/* translators: 1: human-readable time difference, e.g. "3 hours". 2: number of images. */
			esc_html__('Last scanned %1$s ago (%2$d images).', 'ipm-image-attachment-report'),
			human_time_diff($scan_meta['timestamp'], time()),
			(int) $scan_meta['total']
		);
		echo '<p>' . $when . '</p>';
	} else {
		echo '<p>' . esc_html__('No scan has been run yet.', 'ipm-image-attachment-report') . '</p>';
	}
	echo '<form method="post">';
	wp_nonce_field('iar_scan_action', 'iar_scan_nonce');
	echo "<button type='submit' name='iar_scan' value='1' class='button button-primary'>" .
		esc_html__('Run scan', 'ipm-image-attachment-report') . '</button>';
	echo '<p class="description iiar-desc">' . esc_html__('Scanning inspects every image attachment against your content and can take a while on large libraries. Filtering and pagination below use the cached results and do not re-scan.', 'ipm-image-attachment-report') . '</p>';
	echo '</form></div>';
}

/**
 * Lowercased file extension, with jpeg normalised to jpg so they count as one type.
 */
function iar_get_file_type($filename) {
	$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
	return $ext === 'jpeg' ? 'jpg' : $ext;
}

function iar_get_available_file_types($scan_data) {
	$types = [];
	foreach ($scan_data as $record) {
		$type = iar_get_file_type($record['filename']);
		if ($type !== '') {
			$types[$type] = true;
		}
	}
	$types = array_keys($types);
	sort($types);
	return $types;
}

function iar_render_filters($post_types, $excluded_post_types, $lowres_filter, $available_file_types, $excluded_file_types) {
	echo "<div class='filters-wrap'><form method='get' id='filter-form'>";
	echo "<input type='hidden' name='page' value='ipm-image-attachment-report'>";

	echo '<div><p><strong>' . esc_html__('Exclude post types:', 'ipm-image-attachment-report') . '</strong></p>';
	echo '<div class="iiar-filter-group">';
	foreach ($post_types as $type) {
		$checked = in_array($type, $excluded_post_types, true) ? 'checked' : '';
		$label = $type === 'wp_block' ? esc_html__('Pattern', 'ipm-image-attachment-report') : esc_html(ucfirst($type));
		echo "<label><input type='checkbox' name='exclude_post_types[]' value='" . esc_attr($type) . "' {$checked}> {$label}</label>";
	}
	echo '</div></div>';

	echo '<div><p><strong>' . esc_html__('Exclude files by type:', 'ipm-image-attachment-report') . '</strong></p>';
	echo '<div class="iiar-filter-group">';
	foreach ($available_file_types as $type) {
		$checked = in_array($type, $excluded_file_types, true) ? 'checked' : '';
		echo "<label><input type='checkbox' name='exclude_types[]' value='" . esc_attr($type) . "' {$checked}> " . esc_html(strtoupper($type)) . '</label>';
	}
	echo '</div></div>';

	echo "<div><p><strong>" . esc_html__('Filter by resolution:', 'ipm-image-attachment-report') . "</strong></p><p><label>
			<input type='checkbox' name='lowres' value='1' " . ($lowres_filter ? 'checked' : '') . '> ' .
		esc_html__('Show only low-resolution images (below 1 MP)', 'ipm-image-attachment-report') . '
		  </label></p></div>';

	echo '</form></div>';
}

function iar_quality_label($quality) {
	switch ($quality) {
		case 'good':
			return __('Good', 'ipm-image-attachment-report');
		case 'medium':
			return __('Medium', 'ipm-image-attachment-report');
		default:
			return __('Low', 'ipm-image-attachment-report');
	}
}

function iar_render_row($attachment_id, $record, $selected_types, $row_number) {
	$attachment_link = get_edit_post_link($attachment_id);
	$used_in_html = iar_render_used_in($record['used_in'], $selected_types);
	$thumbnail = wp_get_attachment_image($attachment_id, 'thumbnail', false, ['class' => 'thumb-preview']);

	$resolution_label = sprintf(
		/* translators: 1: quality label (Low/Medium/Good). 2: megapixel value. */
		__('%1$s (%2$s MP)', 'ipm-image-attachment-report'),
		iar_quality_label($record['quality']),
		$record['area_mp']
	);

	echo '<tr>
			<td class="row-number">' . esc_html($row_number) . '</td>
			<td class="toggle-thumbnail">' . $thumbnail . '</td>
			<td><a href="' . esc_url($attachment_link) . '">' . esc_html($record['filename']) . '</a></td>
			<td>' . $used_in_html . '</td>
			<td>' . esc_html($record['width'] . ' x ' . $record['height']) . '</td>
			<td><span class="' . esc_attr($record['quality'] . '-res') . '">' . esc_html($resolution_label) . '</span></td>
		  </tr>';
}

function iar_render_used_in($hits, $selected_types) {
	$lines = [];

	foreach ($hits as $hit) {
		if ($hit['type'] === 'post') {
			$post_type = get_post_type($hit['post_id']);
			if (!$post_type || !in_array($post_type, $selected_types, true)) {
				continue;
			}
			$title = get_the_title($hit['post_id']);
			$edit_link = get_edit_post_link($hit['post_id']);
			$lines[] = '<a href="' . esc_url($edit_link) . '">' . esc_html($title) . '</a> [' . esc_html($post_type) . '] (' . esc_html(implode(', ', $hit['methods'])) . ')';
		} elseif ($hit['type'] === 'user') {
			$user = get_userdata($hit['user_id']);
			$name = $user ? $user->display_name : __('Unknown user', 'ipm-image-attachment-report');
			$lines[] = esc_html__('User:', 'ipm-image-attachment-report') . ' ' . esc_html($name) . ' (' . esc_html(implode(', ', $hit['methods'])) . ')';
		} elseif ($hit['type'] === 'term') {
			$term = get_term($hit['term_id']);
			$name = ($term && !is_wp_error($term)) ? $term->name : __('Unknown term', 'ipm-image-attachment-report');
			$lines[] = esc_html__('Term:', 'ipm-image-attachment-report') . ' ' . esc_html($name) . ' (' . esc_html(implode(', ', $hit['methods'])) . ')';
		}
	}

	return $lines ? implode('<br>', $lines) : esc_html__('Not found in content', 'ipm-image-attachment-report');
}

function iar_render_pagination($current_page, $total_pages) {
	if ($total_pages <= 1) {
		return;
	}

	echo '<div class="iiar-tablenav tablnav">';
	for ($page = 1; $page <= $total_pages; $page++) {
		$url = esc_url(add_query_arg('paged', $page));
		if ($page === $current_page) {
			echo '<span class="paging-input button disabled">' . esc_html($page) . '</span> ';
		} else {
			echo '<a class="next-page button" href="' . $url . '">' . esc_html($page) . '</a> ';
		}
	}
	echo '</div>';
}
