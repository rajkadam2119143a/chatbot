<?php
/**
 * Policy Assistant - guided chatbot for the policy admin tasks.
 *
 * Lets a policy admin add, edit, search, archive and restore policies by chatting
 * instead of using the admin forms. Add / Edit reuse the existing form handlers
 * (wpshout_save_post_if_submitted / wpshout_edit_save_post_if_submitted), so a policy
 * created here is stored exactly like one created from the Add Policy page.
 *
 * Requests are plain POSTs to the site front end (not admin-ajax.php) because this
 * theme signs users in on front-end requests only (see check_login_user()), and that
 * does not run for admin-ajax.php.
 *
 * Loaded from functions.php. Only users allowed by hr_user_can_manage_policies() get
 * the widget and the endpoint.
 */

if (!defined('ABSPATH')) {
	exit;
}

define('HR_CHATBOT_MAX_BYTES', 4 * 1024 * 1024);
define('HR_CHATBOT_PER_PAGE', 6);
define('HR_CHATBOT_UNDO_WINDOW', DAY_IN_SECONDS); // how long an edit can be undone
define('HR_CHATBOT_UNDO_KEEP', 5);                // snapshots kept per policy within that window
define('HR_CHATBOT_LOG_KEEP', 10);                // changes shown in "Revert / undo a mistake" (newest first)
define('HR_CHATBOT_STALE_MONTHS', 12);            // a policy date older than this is flagged by the health check
define('HR_CHATBOT_BULK_MAX', 100);               // policies one bulk action may touch
define('HR_CHATBOT_GAPS_KEEP', 50);               // distinct "no result" searches remembered
define('HR_CHATBOT_ASK_MAX', 30);                 // policies one question / category listing returns

//----------------------------------------------------------------------------
// Front-end assets + container
//----------------------------------------------------------------------------

add_action('wp_enqueue_scripts', 'hr_chatbot_enqueue', 20);
/** Tasks open to every signed-in user (the employee "ask a question" mode). */
function hr_chatbot_staff_tasks()
{
	return array('ask');
}

function hr_chatbot_enqueue()
{
	if (!is_user_logged_in()) {
		return;
	}

	$dir = get_stylesheet_directory();
	$uri = get_stylesheet_directory_uri();

	wp_enqueue_style('hr-chatbot-font', 'https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap', array(), null);
	wp_enqueue_style('hr-chatbot-tokens', $uri . '/css/tokens.css', array(), filemtime($dir . '/css/tokens.css'));
	wp_enqueue_style('hr-chatbot', $uri . '/css/policy-chatbot.css', array('hr-chatbot-tokens'), filemtime($dir . '/css/policy-chatbot.css'));
	wp_enqueue_script('hr-chatbot', $uri . '/js/policy-chatbot.js', array(), filemtime($dir . '/js/policy-chatbot.js'), true);

	$user = wp_get_current_user();
	wp_localize_script('hr-chatbot', 'HR_CHATBOT', array(
		'endpoint'   => home_url('/'),
		'nonce'      => wp_create_nonce('hr_chatbot'),
		'maxBytes'   => min(HR_CHATBOT_MAX_BYTES, wp_max_upload_size()),
		'postMax'    => (int) wp_convert_hr_to_bytes(ini_get('post_max_size')),
		'userName'   => $user->first_name ? $user->first_name : $user->display_name,
		'userId'     => (int) $user->ID,
		'canManage'  => hr_user_can_manage_policies(),
		'categories' => hr_chatbot_categories(),
		'links'      => array(
			'add'      => home_url('/admin/add'),
			'search'   => home_url('/admin/search'),
			'archives' => home_url('/admin/archives'),
		),
	));
}

add_action('wp_footer', 'hr_chatbot_container');
function hr_chatbot_container()
{
	if (is_user_logged_in()) {
		echo '<div id="hr-chatbot"></div>';
	}
}

/** Categories as [ {id, name} ] with the parent path in the name ("Parent > Child"). */
function hr_chatbot_categories()
{
	$terms = get_categories(array('hide_empty' => false, 'exclude' => 1, 'orderby' => 'name'));
	$by_id = array();
	foreach ($terms as $t) {
		$by_id[$t->term_id] = $t;
	}

	$out = array();
	foreach ($terms as $t) {
		$out[] = array(
			'id'   => (int) $t->term_id,
			'name' => hr_chatbot_category_path($t, $by_id),
		);
	}

	usort($out, function ($a, $b) {
		return strcasecmp($a['name'], $b['name']);
	});
	return $out;
}

/** "Parent > Child" name of a category. $by_id maps term id => term for the whole tree. */
function hr_chatbot_category_path($t, $by_id)
{
	$path  = array($t->name);
	$guard = 0;
	for ($p = $t->parent; $p && isset($by_id[$p]) && $guard < 10; $p = $by_id[$p]->parent, $guard++) {
		array_unshift($path, $by_id[$p]->name);
	}
	return wp_specialchars_decode(implode(' > ', $path), ENT_QUOTES);
}

//----------------------------------------------------------------------------
// Request handling
//----------------------------------------------------------------------------

add_action('wp_loaded', 'hr_chatbot_handle_request');
function hr_chatbot_handle_request()
{
	if (empty($_POST['hr_chatbot']) || is_admin()) {
		return;
	}

	nocache_headers();

	// wp_die() (used by the add handler if an upload fails) must answer in JSON, not HTML.
	add_filter('wp_die_handler', function () {
		return 'hr_chatbot_die_handler';
	});

	// Any signed-in user may ask questions about the policies; everything else is for policy admins.
	$task = sanitize_key($_POST['hr_chatbot']);
	if (!is_user_logged_in() || (!hr_user_can_manage_policies() && !in_array($task, hr_chatbot_staff_tasks(), true))) {
		hr_chatbot_reply(array('ok' => false, 'code' => 'forbidden', 'message' => 'You do not have access to manage policies.'), 403);
	}
	if (!wp_verify_nonce(isset($_POST['nonce']) ? $_POST['nonce'] : '', 'hr_chatbot')) {
		hr_chatbot_reply(array('ok' => false, 'code' => 'nonce', 'message' => 'This chat session has expired. Please refresh the page and try again.'), 403);
	}

	switch ($task) {
		case 'ask':
			hr_chatbot_task_ask();
		case 'analyze':
			hr_chatbot_task_analyze();
		case 'versions':
			hr_chatbot_task_versions();
		case 'version_restore':
			hr_chatbot_task_version_restore();
		case 'search':
			hr_chatbot_task_search();
		case 'get':
			hr_chatbot_task_get();
		case 'add':
			hr_chatbot_task_add();
		case 'edit':
			hr_chatbot_task_edit();
		case 'archive':
			hr_chatbot_task_archive(true);
		case 'restore':
			hr_chatbot_task_archive(false);
		case 'recent':
			hr_chatbot_task_recent();
		case 'undo':
			hr_chatbot_task_undo();
		case 'cats':
			hr_chatbot_cat_reply(empty($_POST['policies']) ? array() : array('policies' => hr_chatbot_menu_policies()));
		case 'cat_add':
			hr_chatbot_task_cat_add();
		case 'cat_edit':
			hr_chatbot_task_cat_edit();
		case 'cat_order':
			hr_chatbot_task_cat_order();
		case 'cat_move':
			hr_chatbot_task_cat_move_policies();
		case 'cat_delete':
			hr_chatbot_task_cat_delete();
		case 'health':
			hr_chatbot_task_health();
		case 'bulk':
			hr_chatbot_task_bulk();
		case 'export':
			hr_chatbot_task_export();
		case 'schedule':
			hr_chatbot_task_schedule();
		case 'schedules':
			hr_chatbot_task_schedules();
		case 'unschedule':
			hr_chatbot_task_unschedule();
		case 'gaps':
			hr_chatbot_task_gaps(false);
		case 'gaps_clear':
			hr_chatbot_task_gaps(true);
	}

	hr_chatbot_reply(array('ok' => false, 'code' => 'unknown', 'message' => 'Unknown request.'), 400);
}

function hr_chatbot_reply($data, $status = 200)
{
	while (ob_get_level()) {
		ob_end_clean(); // drop stray notices so the JSON stays valid
	}
	// A fresh token with every answer, so a chat left open for hours (or a login that changed
	// mid-session) recovers on its own instead of failing.
	if (is_user_logged_in()) {
		$data['nonce'] = wp_create_nonce('hr_chatbot');
	}
	wp_send_json($data, $status);
}

function hr_chatbot_die_handler($message)
{
	if (is_wp_error($message)) {
		$message = $message->get_error_message();
	}
	$message = is_scalar($message) ? trim(wp_strip_all_tags((string) $message)) : '';
	hr_chatbot_reply(array('ok' => false, 'code' => 'save', 'message' => $message !== '' ? $message : 'Something went wrong while saving the policy.'));
}

/** Posted text field, unslashed (WordPress slashes $_POST). */
function hr_chatbot_field($key, $default = '')
{
	return isset($_POST[$key]) && is_scalar($_POST[$key]) ? trim(wp_unslash((string) $_POST[$key])) : $default;
}

//----------------------------------------------------------------------------
// Policy helpers
//----------------------------------------------------------------------------

function hr_chatbot_load_policy($id)
{
	$post = $id ? get_post((int) $id) : null;
	if (!$post || $post->post_type !== 'post' || !in_array($post->post_status, array('publish', 'private'), true)) {
		return null;
	}
	return $post;
}

function hr_chatbot_format_policy($post)
{
	$id    = $post->ID;
	$cats  = array_values(array_filter(get_the_category($id), function ($c) {
		return (int) $c->term_id !== 1;
	}));
	$file  = get_post_meta($id, 'policy_download_url', true);
	$pdf   = get_post_meta($id, 'doc_to_pdf', true);
	$tags  = wp_get_post_tags($id, array('fields' => 'names'));

	return array(
		'id'          => $id,
		'slug'        => $post->post_name,
		'title'       => wp_specialchars_decode(get_the_title($id), ENT_QUOTES),
		'url'         => get_permalink($id),
		'status'      => $post->post_status === 'private' ? 'private' : 'public',
		'category_id' => $cats ? (int) $cats[0]->term_id : 0,
		'category'    => $cats ? wp_specialchars_decode($cats[0]->name, ENT_QUOTES) : '',
		'keywords'    => implode(', ', $tags),
		'purpose'     => $post->post_excerpt,
		'date'        => (string) get_post_meta($id, 'post_policy_date', true),
		'file'        => $file ? basename($file) : '',
		'pdf'         => $pdf ? basename($pdf) : '',
		'archived'    => (bool) get_post_meta($id, 'is_archived', true),
	);
}

function hr_chatbot_archive_meta_query($archived)
{
	if ($archived) {
		return array(array('key' => 'is_archived', 'value' => '1', 'compare' => '='));
	}
	return array(
		'relation' => 'OR',
		array('key' => 'is_archived', 'compare' => 'NOT EXISTS'),
		array('key' => 'is_archived', 'value' => '1', 'compare' => '!='),
	);
}

//----------------------------------------------------------------------------
// Tasks
//----------------------------------------------------------------------------

function hr_chatbot_task_search()
{
	$q        = hr_chatbot_field('q');
	$cat      = absint(hr_chatbot_field('cat', 0));
	$archived = !empty($_POST['archived']);
	$page     = max(1, absint(hr_chatbot_field('page', 1)));

	$args = array(
		'post_type'      => 'post',
		'post_status'    => array('publish', 'private'),
		'posts_per_page' => HR_CHATBOT_PER_PAGE,
		'paged'          => $page,
		'orderby'        => $q !== '' ? 'title' : 'date',
		'order'          => $q !== '' ? 'ASC' : 'DESC',
		'meta_query'     => hr_chatbot_archive_meta_query($archived),
	);
	if ($cat) {
		$args['category__in'] = array($cat);
	}
	$access = hr_chatbot_field('access');
	if ($access === 'public' || $access === 'private') {
		$args['post_status'] = array($access === 'public' ? 'publish' : 'private');
	}

	// Match on name (qirolab_posts_where handles search_title) or keyword/tag.
	// 's' is deliberately not used: the theme rewrites every search query.
	if ($q !== '') {
		$base = $args;
		unset($base['paged']);
		$base['posts_per_page'] = -1;
		$base['fields']         = 'ids';
		$base['no_found_rows']  = true;

		$ids   = (new WP_Query($base + array('search_title' => $q)))->posts;
		$terms = get_terms(array('taxonomy' => 'post_tag', 'name__like' => $q, 'fields' => 'ids', 'hide_empty' => false));
		if ($terms && !is_wp_error($terms)) {
			$ids = array_merge($ids, (new WP_Query($base + array('tag__in' => $terms)))->posts);
		}
		$args['post__in'] = $ids ? array_values(array_unique($ids)) : array(0);
	}

	$query = new WP_Query($args);
	$items = array();
	foreach ($query->posts as $post) {
		$items[] = hr_chatbot_format_policy($post);
	}

	hr_chatbot_reply(array(
		'ok'       => true,
		'items'    => $items,
		'total'    => (int) $query->found_posts,
		'page'     => $page,
		'has_more' => $page < (int) $query->max_num_pages,
	));
}

function hr_chatbot_task_get()
{
	$post = hr_chatbot_load_policy(absint(hr_chatbot_field('id', 0)));
	if (!$post) {
		hr_chatbot_reply(array('ok' => false, 'code' => 'notfound', 'message' => 'I could not find that policy.'));
	}
	hr_chatbot_reply(array('ok' => true, 'policy' => hr_chatbot_format_policy($post)));
}

function hr_chatbot_task_archive($archive)
{
	$post = hr_chatbot_load_policy(absint(hr_chatbot_field('id', 0)));
	if (!$post) {
		hr_chatbot_reply(array('ok' => false, 'code' => 'notfound', 'message' => 'I could not find that policy.'));
	}

	$was = array(
		'is' => metadata_exists('post', $post->ID, 'is_archived') ? get_post_meta($post->ID, 'is_archived', true) : null,
		'at' => metadata_exists('post', $post->ID, 'archived_at') ? get_post_meta($post->ID, 'archived_at', true) : null,
	);

	// Same meta keys as the archive_post / restore_post handlers in functions.php.
	if ($archive) {
		update_post_meta($post->ID, 'is_archived', 1);
		update_post_meta($post->ID, 'archived_at', current_time('mysql'));
	} else {
		delete_post_meta($post->ID, 'is_archived');
		delete_post_meta($post->ID, 'archived_at');
	}
	clean_post_cache($post->ID);

	$title = wp_specialchars_decode(get_the_title($post->ID), ENT_QUOTES);
	if ((bool) $was['is'] !== $archive) {
		hr_chatbot_log_add(
			$archive ? 'archive' : 'restore',
			$archive ? 'Policy archived' : 'Policy restored from the archive',
			$title,
			array(array('Status', $archive ? 'Archived' : 'Active', $archive ? 'Active' : 'Archived')),
			array('post_id' => $post->ID, 'was' => $was)
		);
	}
	hr_chatbot_reply(array(
		'ok'      => true,
		'message' => $archive ? '"' . $title . '" has been archived.' : '"' . $title . '" has been restored.',
		'policy'  => hr_chatbot_format_policy(get_post($post->ID)),
	));
}

/**
 * Validates the fields shared by add and edit. Returns an error string, or '' when fine.
 *
 * @param string|null $current_excerpt Existing Purpose/Scope when editing; an unchanged one
 *                                     is not re-checked (older policies may exceed 60 words).
 */
function hr_chatbot_validate_common($current_excerpt = null)
{
	$title = hr_chatbot_field('title');
	if (strlen($title) < 3) {
		return 'Titles must be at least three characters long.';
	}

	$cat = absint(hr_chatbot_field('cat', 0));
	if (!$cat || $cat === 1 || !term_exists($cat, 'category')) {
		return 'Please choose a valid category.';
	}

	if (!in_array(hr_chatbot_field('post_status'), array('public', 'private'), true)) {
		return 'Access must be Public or Private.';
	}

	$excerpt = hr_chatbot_field('post_excerpt');
	if ($excerpt !== $current_excerpt && str_word_count($excerpt) > 60) {
		return 'Purpose/Scope can be 60 words at most.';
	}

	$date = hr_chatbot_field('post_policy_date');
	if ($date !== '') {
		if (!preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $date, $m) || !checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
			return 'Policy date must be a real date in dd/mm/yyyy format.';
		}
	}

	// Files are checked here, before anything is saved: the form handlers validate files only
	// after updating the policy text, so a bad file would otherwise leave a half-saved edit.
	$labels = array('post_file' => 'The policy file', 'post_file1' => 'The PDF version');
	foreach ($labels as $key => $label) {
		if (!isset($_FILES[$key]) || $_FILES[$key]['error'] === UPLOAD_ERR_NO_FILE) {
			continue;
		}
		$err = $_FILES[$key]['error'];
		if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
			return $label . ' is larger than the server allows (' . size_format(wp_max_upload_size()) . ').';
		}
		if ($err !== UPLOAD_ERR_OK) {
			return $label . ' did not upload completely (error ' . (int) $err . '). Please try again.';
		}
		$ok = validate_policy_file($_FILES[$key]);
		if ($ok !== true) {
			return $label . ': ' . $ok;
		}
	}

	// The rule from the form's JavaScript: a Word/Excel/CSV file needs its PDF version.
	$main = isset($_FILES['post_file']) && $_FILES['post_file']['size'] > 0 ? $_FILES['post_file'] : null;
	$pdf  = isset($_FILES['post_file1']) && $_FILES['post_file1']['size'] > 0;
	if ($main) {
		$ext = strtolower(pathinfo($main['name'], PATHINFO_EXTENSION));
		if ($ext !== 'pdf' && !$pdf) {
			return 'Please also upload the PDF version of that file.';
		}
	}

	return '';
}

/**
 * Runs one of the existing form handlers and turns its alert HTML into a result.
 *
 * @return array [bool success, string message]
 */
function hr_chatbot_run_form_handler($callback)
{
	$_POST += array('post_tags' => '', 'post_excerpt' => '', 'post_policy_date' => '');

	// The Add/Edit pages post a hidden "hidden-1" token that matches $_SESSION['hidden_admin'];
	// supply the same pair in case the live handler checks it.
	if (!session_id()) {
		session_start();
	}
	$token = md5(uniqid(mt_rand(), true));
	$_SESSION['hidden_admin'] = $token;
	$_POST['hidden-1']        = $token;

	ob_start();
	call_user_func($callback);
	$html = ob_get_clean();

	$success = strpos($html, 'alert-success') !== false;
	$text    = preg_replace('#<button.*?</button>#s', '', $html);
	$text    = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags($text)));
	$text    = preg_replace('/^(Success|Error)!\s*/i', '', $text);

	return array($success, $text !== '' ? $text : ($success ? 'Done.' : 'The policy could not be saved.'));
}

function hr_chatbot_task_add()
{
	$error = hr_chatbot_validate_common();
	if (!$error && !isset($_FILES['post_file']) && !isset($_FILES['post_file1'])) {
		$error = 'Please attach the policy file.';
	}
	if ($error) {
		hr_chatbot_reply(array('ok' => false, 'code' => 'validation', 'message' => $error));
	}

	global $wpdb;
	$last_id = (int) $wpdb->get_var("SELECT MAX(ID) FROM {$wpdb->posts}");

	$_POST['_wpnonce'] = wp_create_nonce('wps-frontend-post');
	list($success, $message) = hr_chatbot_run_form_handler('wpshout_save_post_if_submitted');

	if (!$success) {
		hr_chatbot_reply(array('ok' => false, 'code' => 'save', 'message' => $message));
	}

	$found = get_posts(array(
		'post_type'   => 'post',
		'post_status' => array('publish', 'private'),
		'title'       => hr_chatbot_field('title'),
		'orderby'     => 'ID',
		'order'       => 'DESC',
		'numberposts' => 1,
	));
	$policy = ($found && $found[0]->ID > $last_id) ? hr_chatbot_format_policy($found[0]) : null;

	if ($policy) {
		hr_chatbot_log_add(
			'add',
			'Policy added',
			$policy['title'],
			array(array('Policy', 'Added to ' . ($policy['category'] ? $policy['category'] : 'no category'), 'Did not exist')),
			array('post_id' => $policy['id'], 'doc_id' => (int) get_post_meta($policy['id'], 'document_revisions_details', true))
		);
	}

	hr_chatbot_reply(array('ok' => true, 'message' => 'The policy has been added.', 'policy' => $policy));
}

function hr_chatbot_task_edit()
{
	$post = hr_chatbot_load_policy(absint(hr_chatbot_field('id', 0)));
	if (!$post) {
		hr_chatbot_reply(array('ok' => false, 'code' => 'notfound', 'message' => 'I could not find that policy.'));
	}

	$error = hr_chatbot_validate_common(trim($post->post_excerpt));
	if ($error) {
		hr_chatbot_reply(array('ok' => false, 'code' => 'validation', 'message' => $error));
	}

	// Remember how the policy looks now so this edit can be undone. Saved before the form
	// handler runs, so even an edit that stops half way can be put back.
	$before = hr_chatbot_capture($post->ID);
	$user   = wp_get_current_user();
	$list   = hr_chatbot_undo_list($post->ID);
	$list[] = array(
		'id'    => wp_generate_password(8, false),
		'time'  => time(),
		'user'  => $user->ID,
		'name'  => $user->display_name,
		'state' => $before,
	);
	$list = array_slice($list, -HR_CHATBOT_UNDO_KEEP);
	hr_chatbot_undo_save($post->ID, $list);

	// The form handler locates the policy by slug, like the Edit Policy page does.
	$_POST['add_data'] = $post->post_name;
	$_POST['_wpnonce'] = wp_create_nonce('wps-frontend-post-edit');
	list($success, $message) = hr_chatbot_run_form_handler('wpshout_edit_save_post_if_submitted');

	hr_chatbot_clean_caches($post->ID);
	$changed = hr_chatbot_capture($post->ID) !== $before;
	if (!$changed) {
		array_pop($list); // nothing was saved, so there is nothing to undo
		hr_chatbot_undo_save($post->ID, $list);
	}

	if (!$success) {
		if ($changed) {
			$message .= ' Part of the change was saved before this happened. You can put it back with "Undo a recent change".';
		}
		hr_chatbot_reply(array('ok' => false, 'code' => 'save', 'message' => $message));
	}

	hr_chatbot_reply(array(
		'ok'      => true,
		'message' => 'The policy has been updated.',
		'policy'  => hr_chatbot_format_policy(get_post($post->ID)),
		'undo'    => $changed,
	));
}

//----------------------------------------------------------------------------
// Undo: an edit can be reverted for one day (HR_CHATBOT_UNDO_WINDOW)
//----------------------------------------------------------------------------

/** Post meta an edit can change. Files uploaded by an edit stay in the Media Library. */
function hr_chatbot_undo_meta_keys()
{
	return array('post_policy_date', 'policy_download_url', 'doc_to_pdf', 'document_revisions_details');
}

function hr_chatbot_clean_caches($post_id)
{
	clean_post_cache($post_id);
	$doc_id = (int) get_post_meta($post_id, 'document_revisions_details', true);
	if ($doc_id) {
		clean_post_cache($doc_id);
	}
}

/** Everything an edit can change on a policy (and its document), so it can be put back. */
function hr_chatbot_capture($post_id)
{
	$post = get_post($post_id);

	$cats = array_map('intval', wp_get_post_categories($post_id));
	sort($cats);
	$tags = wp_get_post_tags($post_id, array('fields' => 'names'));
	sort($tags);

	$meta = array();
	foreach (hr_chatbot_undo_meta_keys() as $key) {
		$meta[$key] = metadata_exists('post', $post_id, $key) ? get_post_meta($post_id, $key, true) : null;
	}

	$doc    = null;
	$files  = array();
	$revs   = array();
	$doc_id = (int) $meta['document_revisions_details'];
	if ($doc_id && get_post_type($doc_id) === 'document') {
		global $wpdb;
		$d     = get_post($doc_id);
		$doc   = array(
			'id'      => $doc_id,
			'title'   => $d->post_title,
			'content' => $d->post_content,
			'wpdr'    => metadata_exists('post', $doc_id, '_wpdr_document') ? get_post_meta($doc_id, '_wpdr_document', true) : null,
		);
		// Files attached to the document. The policy page falls back to the newest of these when
		// the policy has no policy_download_url, so a file added by an edit must be detached on undo.
		$files = array_map('intval', $wpdb->get_col($wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_parent = %d AND post_type = 'attachment' ORDER BY ID",
			$doc_id
		)));
		// The "Related Files" sidebar lists the document's revisions (WP Document Revisions).
		$revs = hr_chatbot_doc_revisions($doc_id);
	}

	return array(
		'title'   => $post->post_title,
		'name'    => $post->post_name,
		'excerpt' => $post->post_excerpt,
		'status'  => $post->post_status,
		'cats'    => $cats,
		'tags'    => $tags,
		'meta'    => $meta,
		'doc'     => $doc,
		'files'   => $files,
		'revs'    => $revs,
	);
}

/** IDs of a document's revisions: the entries of the policy page's "Related Files" list. */
function hr_chatbot_doc_revisions($doc_id)
{
	global $wpdb;
	return array_map('intval', $wpdb->get_col($wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts} WHERE post_parent = %d AND post_type = 'revision' ORDER BY ID",
		$doc_id
	)));
}

/** Snapshots still inside the undo window, oldest first. */
function hr_chatbot_undo_list($post_id)
{
	$list = get_post_meta($post_id, '_hr_chatbot_undo', true);
	if (!is_array($list)) {
		return array();
	}
	$min = time() - HR_CHATBOT_UNDO_WINDOW;
	return array_values(array_filter($list, function ($s) use ($min) {
		return isset($s['id'], $s['time'], $s['state']) && $s['time'] > $min;
	}));
}

function hr_chatbot_undo_save($post_id, $list)
{
	if ($list) {
		update_post_meta($post_id, '_hr_chatbot_undo', wp_slash($list)); // update_post_meta unslashes
	} else {
		delete_post_meta($post_id, '_hr_chatbot_undo');
	}
}

/** The fields an admin cares about, as readable text. */
function hr_chatbot_describe($s)
{
	$cats = array_values(array_diff($s['cats'], array(1)));
	$file = $s['meta']['policy_download_url'];
	$pdf  = $s['meta']['doc_to_pdf'];
	if (!$file && !empty($s['files'])) {
		$file = (string) get_post_meta(max($s['files']), '_wp_attached_file', true); // older policies: newest attached file
	}

	// Snapshots from before revisions were tracked have no count, so they are never compared.
	$related = isset($s['revs']) && $s['doc'] ? count($s['revs']) . ' ' . (count($s['revs']) === 1 ? 'entry' : 'entries') : null;

	return array(
		'Name'        => wp_specialchars_decode($s['title'], ENT_QUOTES),
		'Category'    => $cats ? wp_specialchars_decode(get_cat_name($cats[0]), ENT_QUOTES) : '',
		'Keywords'    => implode(', ', $s['tags']),
		'Purpose'     => (string) $s['excerpt'],
		'Policy file' => $file ? basename($file) : '',
		'PDF'         => $pdf ? basename($pdf) : '',
		'Related Files' => $related,
		'Access'      => $s['status'] === 'private' ? 'Private' : 'Public',
		'Policy date' => (string) $s['meta']['post_policy_date'],
	);
}

/** Rows [label, now, earlier] for the fields that differ between two states. */
function hr_chatbot_diff($now, $earlier)
{
	$a    = hr_chatbot_describe($now);
	$b    = hr_chatbot_describe($earlier);
	$rows = array();
	foreach ($a as $label => $value) {
		if ($value === null || $b[$label] === null) {
			continue;
		}
		if ($value !== $b[$label]) {
			$rows[] = array($label, $value, $b[$label]);
		}
	}
	return $rows;
}

/** Puts a policy back as captured. Returns an error string, or '' when done. */
function hr_chatbot_restore($post_id, $s)
{
	$current_doc = (int) get_post_meta($post_id, 'document_revisions_details', true);

	$res = wp_update_post(wp_slash(array(
		'ID'           => $post_id,
		'post_title'   => $s['title'],
		'post_name'    => $s['name'],
		'post_excerpt' => $s['excerpt'],
		'post_status'  => $s['status'],
	)), true);
	if (is_wp_error($res)) {
		return $res->get_error_message();
	}

	wp_set_post_categories($post_id, $s['cats']);
	wp_set_post_tags($post_id, $s['tags'], false);

	foreach ($s['meta'] as $key => $value) {
		if ($value === null) {
			delete_post_meta($post_id, $key);
		} else {
			update_post_meta($post_id, $key, wp_slash($value));
		}
	}

	if ($s['doc'] && get_post_type($s['doc']['id']) === 'document') {
		wp_update_post(wp_slash(array(
			'ID'           => $s['doc']['id'],
			'post_title'   => $s['doc']['title'],
			'post_content' => $s['doc']['content'],
		)));
		if ($s['doc']['wpdr'] === null) {
			delete_post_meta($s['doc']['id'], '_wpdr_document');
		} else {
			update_post_meta($s['doc']['id'], '_wpdr_document', wp_slash($s['doc']['wpdr']));
		}

		// Remove the "Related Files" entries added since: the edit's own, and the one the update
		// just above created. The files themselves are not deleted.
		if (isset($s['revs'])) {
			foreach (array_diff(hr_chatbot_doc_revisions($s['doc']['id']), $s['revs']) as $rev_id) {
				wp_delete_post_revision($rev_id);
			}
		}

		// Detach the files this edit added (they stay in the Media Library). Without this, a policy
		// that has no policy_download_url keeps showing the newest attached file, i.e. the wrong one.
		if (isset($s['files'])) {
			global $wpdb;
			$added = array_diff(
				array_map('intval', $wpdb->get_col($wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_parent = %d AND post_type = 'attachment'",
					$s['doc']['id']
				))),
				$s['files']
			);
			foreach ($added as $attachment_id) {
				$wpdb->update($wpdb->posts, array('post_parent' => 0), array('ID' => $attachment_id));
				clean_post_cache($attachment_id);
			}
		}
	} elseif (!$s['doc'] && $current_doc && get_post_type($current_doc) === 'document') {
		wp_trash_post($current_doc); // the edit created this document; trash is recoverable
	}

	clean_post_cache($post_id);
	if ($current_doc) {
		clean_post_cache($current_doc);
	}
	return '';
}

/**
 * Everything the Policy Assistant changed in the last 24 hours that can still be undone, newest first:
 * policy edits (a snapshot per policy) and the activity log (everything else). With id: just that
 * policy's last edit. With change: just that log entry.
 */
function hr_chatbot_task_recent()
{
	global $wpdb;

	$change = hr_chatbot_field('change');
	$only   = absint(hr_chatbot_field('id', 0));
	$items  = array();

	if ($change === '') {
		$ids = $only
			? array($only)
			: array_map('intval', $wpdb->get_col($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s", '_hr_chatbot_undo')));

		foreach (array_unique($ids) as $id) {
			$post = hr_chatbot_load_policy($id);
			$list = $post ? hr_chatbot_undo_list($id) : array();
			if (!$list) {
				continue;
			}
			$snap = end($list);
			$rows = hr_chatbot_diff(hr_chatbot_capture($id), $snap['state']);
			if (!$rows) {
				continue;
			}
			$policy  = hr_chatbot_format_policy($post);
			$items[] = array(
				'kind'    => 'edit',
				'label'   => 'Policy edited',
				'title'   => $policy['title'],
				'policy'  => $policy,
				'snap'    => $snap['id'],
				'time'    => $snap['time'],
				'when'    => human_time_diff($snap['time']) . ' ago',
				'expires' => human_time_diff(time(), $snap['time'] + HR_CHATBOT_UNDO_WINDOW),
				'by'      => (string) $snap['name'],
				'changes' => $rows,
				'warn'    => '',
				'more'    => count($list) - 1,
			);
		}
	}

	if (!$only) {
		foreach (hr_chatbot_log_list() as $e) {
			if ($change !== '' && $e['id'] !== $change) {
				continue;
			}
			$post_id = isset($e['data']['post_id']) ? (int) $e['data']['post_id'] : 0;
			$post    = $post_id ? get_post($post_id) : null;
			$items[] = array(
				'kind'    => 'log',
				'change'  => $e['id'],
				'label'   => $e['label'],
				'title'   => $e['title'],
				'policy'  => ($post && in_array($post->post_status, array('publish', 'private'), true)) ? hr_chatbot_format_policy($post) : null,
				'time'    => $e['time'],
				'when'    => human_time_diff($e['time']) . ' ago',
				'expires' => human_time_diff(time(), $e['time'] + HR_CHATBOT_UNDO_WINDOW),
				'by'      => (string) $e['name'],
				'changes' => $e['rows'],
				'warn'    => $e['type'] === 'add' ? 'Undoing this removes the policy again. It goes to the WordPress trash, so it can still be recovered there.' : '',
				'more'    => 0,
			);
		}
	}

	usort($items, function ($a, $b) {
		return $b['time'] - $a['time'];
	});

	hr_chatbot_reply(array('ok' => true, 'items' => array_slice($items, 0, HR_CHATBOT_LOG_KEEP), 'total' => count($items)));
}

function hr_chatbot_task_undo()
{
	if (hr_chatbot_field('change') !== '') {
		hr_chatbot_undo_logged(hr_chatbot_field('change'));
	}

	$post = hr_chatbot_load_policy(absint(hr_chatbot_field('id', 0)));
	if (!$post) {
		hr_chatbot_reply(array('ok' => false, 'code' => 'notfound', 'message' => 'I could not find that policy.'));
	}

	$list = hr_chatbot_undo_list($post->ID);
	if (!$list) {
		hr_chatbot_reply(array('ok' => false, 'code' => 'noundo', 'message' => 'There is nothing left to undo for this policy. A change made with the Policy Assistant can be undone for 24 hours.'));
	}

	$snap = array_pop($list);
	$want = hr_chatbot_field('snap');
	if ($want !== '' && $want !== $snap['id']) {
		hr_chatbot_reply(array('ok' => false, 'code' => 'stale', 'message' => 'This policy was changed again after you looked at it, so I did not undo anything.'));
	}

	$error = hr_chatbot_restore($post->ID, $snap['state']);
	if ($error) {
		hr_chatbot_reply(array('ok' => false, 'code' => 'save', 'message' => $error));
	}
	hr_chatbot_undo_save($post->ID, $list);

	hr_chatbot_reply(array(
		'ok'      => true,
		'message' => 'The policy is back to how it was before the change.',
		'policy'  => hr_chatbot_format_policy(get_post($post->ID)),
		'more'    => count($list),
	));
}

//----------------------------------------------------------------------------
// Categories: the sections of the left-hand menu
//
// The menu lists the top-level categories ordered by their "order" term meta (a category
// without it is left out of the menu), then each one's sub-categories and policies.
//----------------------------------------------------------------------------

/** Policies directly in each category: [term id => count]. Private and archived ones count. */
function hr_chatbot_category_counts()
{
	global $wpdb;
	$rows = $wpdb->get_results("SELECT tt.term_id, COUNT(DISTINCT p.ID) AS n
		FROM {$wpdb->term_relationships} tr
		INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'category'
		INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id AND p.post_type = 'post' AND p.post_status IN ('publish', 'private')
		GROUP BY tt.term_id");
	$out = array();
	foreach ($rows as $r) {
		$out[(int) $r->term_id] = (int) $r->n;
	}
	return $out;
}

/** Top-level categories in the order the menu shows them. Those without an "order" come last. */
function hr_chatbot_sections()
{
	$terms = get_terms(array('taxonomy' => 'category', 'parent' => 0, 'hide_empty' => false, 'exclude' => 1));
	if (is_wp_error($terms)) {
		return array();
	}
	$rows = array();
	foreach ($terms as $t) {
		$has    = metadata_exists('term', $t->term_id, 'order');
		$rows[] = array(
			'id'    => (int) $t->term_id,
			'title' => wp_specialchars_decode($t->name, ENT_QUOTES),
			'has'   => $has,
			'order' => $has ? (string) get_term_meta($t->term_id, 'order', true) : '',
		);
	}
	usort($rows, function ($a, $b) {
		if ($a['has'] !== $b['has']) {
			return $a['has'] ? -1 : 1;
		}
		$c = strcmp($a['order'], $b['order']); // the menu sorts the meta as text, so "10" comes before "2"
		return $c !== 0 ? $c : strcasecmp($a['title'], $b['title']);
	});
	return $rows;
}

/**
 * Puts a top-level category in the menu. $after: 0 = first, -1 = last, or the id of the section
 * it should follow. Sections already in the menu keep their order and are renumbered as
 * 001, 002, ... so the text sort the menu uses matches the number order.
 */
function hr_chatbot_place_section($id, $after)
{
	$ids = array();
	foreach (hr_chatbot_sections() as $s) {
		if ($s['has'] && $s['id'] !== $id) {
			$ids[] = $s['id'];
		}
	}
	$pos = $after > 0 ? array_search($after, $ids, true) : false;
	if ($after === 0) {
		array_unshift($ids, $id);
	} elseif ($pos === false) {
		$ids[] = $id;
	} else {
		array_splice($ids, $pos + 1, 0, array($id));
	}
	foreach ($ids as $i => $term_id) {
		update_term_meta($term_id, 'order', sprintf('%03d', $i + 1));
	}
}

/** Every category with what the chat needs to manage it. */
function hr_chatbot_category_rows()
{
	$terms  = get_categories(array('hide_empty' => false, 'exclude' => 1));
	$counts = hr_chatbot_category_counts();
	$by_id  = array();
	$kids   = array();
	foreach ($terms as $t) {
		$by_id[$t->term_id] = $t;
		$kids[$t->parent]   = isset($kids[$t->parent]) ? $kids[$t->parent] + 1 : 1;
	}

	$rows = array();
	foreach ($terms as $t) {
		$rows[] = array(
			'id'       => (int) $t->term_id,
			'name'     => hr_chatbot_category_path($t, $by_id),
			'title'    => wp_specialchars_decode($t->name, ENT_QUOTES),
			'parent'   => (int) $t->parent,
			'count'    => isset($counts[$t->term_id]) ? $counts[$t->term_id] : 0,
			'children' => isset($kids[$t->term_id]) ? $kids[$t->term_id] : 0,
			'visible'  => $t->parent ? true : metadata_exists('term', $t->term_id, 'order'),
		);
	}
	usort($rows, function ($a, $b) {
		return strcasecmp($a['name'], $b['name']);
	});
	return $rows;
}

/** The policies the menu lists (archived ones are left out, as in the menu), oldest first. */
function hr_chatbot_menu_policies()
{
	$posts = get_posts(array(
		'post_type'     => 'post',
		'post_status'   => array('publish', 'private'),
		'numberposts'   => -1,
		'orderby'       => 'post_date',
		'order'         => 'ASC',
		'meta_query'    => hr_chatbot_archive_meta_query(false),
		'no_found_rows' => true,
	));
	$out = array();
	foreach ($posts as $p) {
		$out[] = array(
			'id'      => (int) $p->ID,
			'title'   => wp_specialchars_decode(get_the_title($p->ID), ENT_QUOTES),
			'cats'    => array_map('intval', wp_get_post_categories($p->ID)),
			'private' => $p->post_status === 'private',
		);
	}
	return $out;
}

/** Every category reply carries the fresh list, so the chat never works from stale data. */
function hr_chatbot_cat_reply($data = array())
{
	$sections = array();
	foreach (hr_chatbot_sections() as $s) {
		if ($s['has']) {
			$sections[] = array('id' => $s['id'], 'title' => $s['title']);
		}
	}
	hr_chatbot_reply(array_merge(array('ok' => true), $data, array('items' => hr_chatbot_category_rows(), 'sections' => $sections)));
}

function hr_chatbot_load_category($id)
{
	$id = absint($id);
	if (!$id || $id === 1) {
		return null;
	}
	$term = get_term($id, 'category');
	return ($term && !is_wp_error($term)) ? $term : null;
}

function hr_chatbot_category_error($code, $message)
{
	hr_chatbot_reply(array('ok' => false, 'code' => $code, 'message' => $message));
}

/** Friendlier text for the errors WordPress gives when saving a category. */
function hr_chatbot_term_error($err, $name)
{
	if (in_array($err->get_error_code(), array('term_exists', 'duplicate_term_slug'), true)) {
		return 'There is already a category called "' . $name . '" in that place. Please use a different name.';
	}
	return $err->get_error_message();
}

function hr_chatbot_task_cat_add()
{
	$name   = sanitize_text_field(hr_chatbot_field('name'));
	$parent = absint(hr_chatbot_field('parent', 0));

	if (strlen($name) < 2) {
		hr_chatbot_category_error('validation', 'The category name must be at least two characters long.');
	}
	if ($parent && !hr_chatbot_load_category($parent)) {
		hr_chatbot_category_error('validation', 'I could not find the parent category.');
	}

	$orders = hr_chatbot_orders_snapshot();
	$res    = wp_insert_term(wp_slash($name), 'category', array('parent' => $parent)); // wp_insert_term unslashes
	if (is_wp_error($res)) {
		hr_chatbot_category_error('validation', hr_chatbot_term_error($res, $name));
	}

	$id = (int) $res['term_id'];
	if (!$parent) {
		hr_chatbot_place_section($id, -1); // without an "order" the menu would not list it
	}
	hr_chatbot_log_add(
		'cat_add',
		'Category added',
		$name,
		array(array('Category', $name . ' (' . hr_chatbot_where_label($parent) . ')', 'Did not exist')),
		array('term_id' => $id, 'orders' => $orders)
	);
	hr_chatbot_cat_reply(array('message' => '"' . $name . '" has been added.', 'id' => $id));
}

function hr_chatbot_task_cat_edit()
{
	$term = hr_chatbot_load_category(hr_chatbot_field('id', 0));
	if (!$term) {
		hr_chatbot_category_error('notfound', 'I could not find that category.');
	}

	$args = array();
	$name = sanitize_text_field(hr_chatbot_field('name'));
	if ($name !== '') {
		if (strlen($name) < 2) {
			hr_chatbot_category_error('validation', 'The category name must be at least two characters long.');
		}
		$args['name'] = wp_slash($name);
	}

	$moved = false;
	if (hr_chatbot_field('parent') !== '') {
		$parent = absint(hr_chatbot_field('parent'));
		if ($parent === (int) $term->term_id || in_array($parent, array_map('intval', get_term_children($term->term_id, 'category')), true)) {
			hr_chatbot_category_error('validation', 'A category cannot be moved inside itself or inside one of its own sub-categories.');
		}
		if ($parent && !hr_chatbot_load_category($parent)) {
			hr_chatbot_category_error('validation', 'I could not find the category to move it under.');
		}
		if ($parent !== (int) $term->parent) {
			$args['parent'] = $parent;
			$moved          = true;
		}
	}

	if (!$args) {
		hr_chatbot_category_error('validation', 'There is nothing to change.');
	}

	$old_name   = wp_specialchars_decode($term->name, ENT_QUOTES);
	$old_parent = (int) $term->parent;
	$orders     = hr_chatbot_orders_snapshot(array((int) $term->term_id));

	$res = wp_update_term($term->term_id, 'category', $args);
	if (is_wp_error($res)) {
		hr_chatbot_category_error('validation', hr_chatbot_term_error($res, $name !== '' ? $name : $term->name));
	}

	if ($moved && !$args['parent']) {
		hr_chatbot_place_section((int) $term->term_id, -1); // now a main section: list it in the menu
	}

	$rows = array();
	$undo = array('term_id' => (int) $term->term_id, 'orders' => $orders);
	if (isset($args['name']) && $name !== $old_name) {
		$rows[]       = array('Name', $name, $old_name);
		$undo['name'] = $old_name;
	}
	if ($moved) {
		$rows[]         = array('Where', hr_chatbot_where_label($args['parent']), hr_chatbot_where_label($old_parent));
		$undo['parent'] = $old_parent;
	}
	if ($rows) {
		hr_chatbot_log_add('cat_edit', $moved && count($rows) === 1 ? 'Category moved' : (isset($undo['name']) && !$moved ? 'Category renamed' : 'Category renamed and moved'), $old_name, $rows, $undo);
	}
	hr_chatbot_cat_reply(array('message' => 'The category has been updated.'));
}

/** Changes where a main section sits in the menu. */
function hr_chatbot_task_cat_order()
{
	$term = hr_chatbot_load_category(hr_chatbot_field('id', 0));
	if (!$term) {
		hr_chatbot_category_error('notfound', 'I could not find that category.');
	}
	if ($term->parent) {
		hr_chatbot_category_error('validation', 'Only main sections can be re-ordered. Sub-categories are listed alphabetically.');
	}
	$orders = hr_chatbot_orders_snapshot(array((int) $term->term_id));
	$was    = hr_chatbot_section_position((int) $term->term_id);
	hr_chatbot_place_section((int) $term->term_id, (int) hr_chatbot_field('after', -1));
	$now = hr_chatbot_section_position((int) $term->term_id);
	if ($now !== $was) {
		hr_chatbot_log_add('cat_order', 'Menu position changed', wp_specialchars_decode($term->name, ENT_QUOTES), array(array('Menu position', $now, $was)), array('term_id' => (int) $term->term_id, 'orders' => $orders));
	}
	hr_chatbot_cat_reply(array('message' => 'The menu order has been updated.'));
}

/** Moves every policy of a category into another one (so the first can then be deleted). */
function hr_chatbot_task_cat_move_policies()
{
	$from = hr_chatbot_load_category(hr_chatbot_field('id', 0));
	$to   = hr_chatbot_load_category(hr_chatbot_field('to', 0));
	if (!$from || !$to) {
		hr_chatbot_category_error('notfound', 'I could not find that category.');
	}
	if ($from->term_id === $to->term_id) {
		hr_chatbot_category_error('validation', 'Please choose a different category to move the policies into.');
	}

	$ids = get_posts(array(
		'post_type'      => 'post',
		'post_status'    => array('publish', 'private'),
		'category__in'   => array((int) $from->term_id),
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	));
	$moved = array();
	foreach ($ids as $post_id) {
		$before = array_map('intval', wp_get_post_categories($post_id));
		$cats   = array_diff($before, array((int) $from->term_id));
		$cats[] = (int) $to->term_id;
		$cats   = array_values(array_unique($cats));
		wp_set_post_categories($post_id, $cats);
		$moved[$post_id] = array('before' => $before, 'after' => array_map('intval', wp_get_post_categories($post_id)));
	}

	$n         = count($ids);
	$from_name = wp_specialchars_decode($from->name, ENT_QUOTES);
	$to_name   = wp_specialchars_decode($to->name, ENT_QUOTES);
	if ($n) {
		hr_chatbot_log_add(
			'cat_move',
			'Policies moved to another category',
			$from_name,
			array(array('Policies', $n . ($n === 1 ? ' policy' : ' policies') . ' in ' . $to_name, 'In ' . $from_name)),
			array('posts' => $moved, 'from' => (int) $from->term_id, 'to' => (int) $to->term_id)
		);
	}
	hr_chatbot_cat_reply(array('message' => $n . ($n === 1 ? ' policy was' : ' policies were') . ' moved to "' . $to_name . '".', 'moved' => $n));
}

/** Deletes a category, but only an empty one: WordPress would drop its policies into "Uncategorized". */
function hr_chatbot_task_cat_delete()
{
	$term = hr_chatbot_load_category(hr_chatbot_field('id', 0));
	if (!$term) {
		hr_chatbot_category_error('notfound', 'I could not find that category.');
	}

	$counts = hr_chatbot_category_counts();
	$n      = isset($counts[$term->term_id]) ? $counts[$term->term_id] : 0;
	if ($n > 0) {
		hr_chatbot_category_error('hasPolicies', 'It still has ' . $n . ($n === 1 ? ' policy' : ' policies') . ' (including private or archived ones), and deleting it would take them out of the menu.');
	}
	$kids = get_terms(array('taxonomy' => 'category', 'parent' => (int) $term->term_id, 'hide_empty' => false, 'fields' => 'ids'));
	if ($kids && !is_wp_error($kids)) {
		hr_chatbot_category_error('hasChildren', 'It still has ' . count($kids) . ' sub-categor' . (count($kids) === 1 ? 'y' : 'ies') . ' inside it.');
	}

	$name = wp_specialchars_decode($term->name, ENT_QUOTES);
	$kept = array(
		'name'        => $name,
		'slug'        => $term->slug,
		'parent'      => (int) $term->parent,
		'description' => $term->description,
		'order'       => metadata_exists('term', $term->term_id, 'order') ? (string) get_term_meta($term->term_id, 'order', true) : null,
		'where'       => hr_chatbot_where_label((int) $term->parent),
	);
	$res = wp_delete_term($term->term_id, 'category');
	if (!$res || is_wp_error($res)) {
		hr_chatbot_category_error('save', 'WordPress could not delete the category.');
	}
	hr_chatbot_log_add('cat_delete', 'Category deleted', $name, array(array('Category', 'Deleted', $name . ' (' . $kept['where'] . ')')), array('term_id' => (int) $term->term_id, 'kept' => $kept));
	hr_chatbot_cat_reply(array('message' => '"' . $name . '" has been deleted.'));
}

//----------------------------------------------------------------------------
// Activity log: everything except policy edits (which keep their own snapshots).
// Kept for 24 hours so each change can be shown and undone.
//----------------------------------------------------------------------------

define('HR_CHATBOT_LOG_OPTION', 'hr_chatbot_log');

function hr_chatbot_log_list()
{
	$list = get_option(HR_CHATBOT_LOG_OPTION, array());
	if (!is_array($list)) {
		return array();
	}
	$min = time() - HR_CHATBOT_UNDO_WINDOW;
	return array_values(array_filter($list, function ($e) use ($min) {
		return isset($e['id'], $e['time'], $e['type']) && $e['time'] > $min;
	}));
}

function hr_chatbot_log_save($list)
{
	update_option(HR_CHATBOT_LOG_OPTION, array_slice(array_values($list), -HR_CHATBOT_LOG_KEEP), false);
}

/**
 * @param array $rows [[label, now, earlier]] shown to the admin before they undo
 * @param array $data what is needed to put things back
 */
function hr_chatbot_log_add($type, $label, $title, $rows, $data = array(), $by = null)
{
	$user   = wp_get_current_user();
	$list   = hr_chatbot_log_list();
	$list[] = array(
		'id'    => wp_generate_password(8, false),
		'time'  => time(),
		'user'  => $user->ID,
		'name'  => $by !== null ? $by : $user->display_name,
		'type'  => $type,
		'label' => $label,
		'title' => $title,
		'rows'  => $rows,
		'data'  => $data,
	);
	hr_chatbot_log_save($list);
}

/** "A > B > C" for a category. */
function hr_chatbot_term_path($term_id)
{
	$names = array();
	$id    = (int) $term_id;
	for ($guard = 0; $id && $guard < 10; $guard++) {
		$t = get_term($id, 'category');
		if (!$t || is_wp_error($t)) {
			break;
		}
		array_unshift($names, wp_specialchars_decode($t->name, ENT_QUOTES));
		$id = (int) $t->parent;
	}
	return implode(' > ', $names);
}

function hr_chatbot_where_label($parent_id)
{
	return $parent_id ? 'Inside ' . hr_chatbot_term_path($parent_id) : 'Main section of the menu';
}

/** Where a main section sits in the menu, in words. */
function hr_chatbot_section_position($id)
{
	$prev = '';
	foreach (hr_chatbot_sections() as $s) {
		if (!$s['has']) {
			continue;
		}
		if ($s['id'] === $id) {
			return $prev === '' ? 'First in the menu' : 'After ' . $prev;
		}
		$prev = $s['title'];
	}
	return 'Not in the menu';
}

/** The "order" of every main section (null = none), plus any extra ids, so the menu can be put back exactly. */
function hr_chatbot_orders_snapshot($extra = array())
{
	$ids = get_terms(array('taxonomy' => 'category', 'parent' => 0, 'hide_empty' => false, 'exclude' => 1, 'fields' => 'ids'));
	$ids = is_wp_error($ids) ? array() : array_map('intval', $ids);
	$map = array();
	foreach (array_merge($ids, $extra) as $id) {
		$map[$id] = metadata_exists('term', $id, 'order') ? (string) get_term_meta($id, 'order', true) : null;
	}
	return $map;
}

function hr_chatbot_orders_restore($map)
{
	foreach ($map as $id => $value) {
		$t = get_term((int) $id, 'category');
		if (!$t || is_wp_error($t)) {
			continue;
		}
		if ($value === null) {
			delete_term_meta((int) $id, 'order');
		} else {
			update_term_meta((int) $id, 'order', $value);
		}
	}
}

/** A deleted category came back under a new id: point the other log entries at it. */
function hr_chatbot_log_remap($old, $new)
{
	$swap = function ($ids) use ($old, $new) {
		return array_map(function ($c) use ($old, $new) {
			return (int) $c === $old ? $new : $c;
		}, $ids);
	};

	$list = hr_chatbot_log_list();
	foreach ($list as $i => $e) {
		$d = $e['data'];
		foreach (array('term_id', 'parent', 'from', 'to') as $k) {
			if (isset($d[$k]) && (int) $d[$k] === $old) {
				$d[$k] = $new;
			}
		}
		if (isset($d['kept']['parent']) && (int) $d['kept']['parent'] === $old) {
			$d['kept']['parent'] = $new;
		}
		if (isset($d['orders']) && array_key_exists($old, $d['orders'])) {
			$d['orders'][$new] = $d['orders'][$old];
			unset($d['orders'][$old]);
		}
		if (isset($d['posts'])) {
			foreach ($d['posts'] as $pid => $p) {
				$d['posts'][$pid]['before'] = $swap($p['before']);
				$d['posts'][$pid]['after']  = $swap($p['after']);
			}
		}
		$list[$i]['data'] = $d;
	}
	hr_chatbot_log_save($list);
}

function hr_chatbot_undo_logged($id)
{
	$entry = null;
	foreach (hr_chatbot_log_list() as $e) {
		if ($e['id'] === $id) {
			$entry = $e;
		}
	}
	if (!$entry) {
		hr_chatbot_category_error('noundo', 'That change can no longer be undone. It is older than 24 hours, or it was already undone.');
	}

	$res = hr_chatbot_undo_entry($entry);
	if (is_string($res)) {
		hr_chatbot_category_error('cannot', $res);
	}

	// Undoing can rewrite the log (ids of a restored category), so read it again before dropping the entry.
	$list = array_values(array_filter(hr_chatbot_log_list(), function ($e) use ($id) {
		return $e['id'] !== $id;
	}));
	hr_chatbot_log_save($list);

	$post_id = isset($entry['data']['post_id']) ? (int) $entry['data']['post_id'] : 0;
	$post    = $post_id ? get_post($post_id) : null;
	hr_chatbot_cat_reply(array(
		'message' => $res['message'],
		'policy'  => ($post && in_array($post->post_status, array('publish', 'private'), true)) ? hr_chatbot_format_policy($post) : null,
		'more'    => count($list),
	));
}

/** Puts one logged change back. Returns an error string, or array('message' => ...). */
function hr_chatbot_undo_entry($e)
{
	$d = $e['data'];

	switch ($e['type']) {
		case 'add':
			$post = get_post($d['post_id']);
			if (!$post || $post->post_type !== 'post' || $post->post_status === 'trash') {
				return 'That policy no longer exists, so there is nothing to remove.';
			}
			wp_trash_post($post->ID);
			if (!empty($d['doc_id']) && get_post_type($d['doc_id']) === 'document') {
				wp_trash_post($d['doc_id']);
			}
			return array('message' => '"' . $e['title'] . '" has been removed again. It is in the WordPress trash if you ever need it back.');

		case 'archive':
		case 'restore':
			$post = hr_chatbot_load_policy($d['post_id']);
			if (!$post) {
				return 'I could not find that policy any more.';
			}
			foreach (array('is_archived' => $d['was']['is'], 'archived_at' => $d['was']['at']) as $key => $value) {
				if ($value === null) {
					delete_post_meta($post->ID, $key);
				} else {
					update_post_meta($post->ID, $key, $value);
				}
			}
			clean_post_cache($post->ID);
			return array('message' => $e['type'] === 'archive' ? '"' . $e['title'] . '" is active again.' : '"' . $e['title'] . '" is back in the archive.');

		case 'cat_add':
			$term = hr_chatbot_load_category($d['term_id']);
			if (!$term) {
				return 'That category no longer exists.';
			}
			$counts = hr_chatbot_category_counts();
			if (!empty($counts[$term->term_id])) {
				return 'It now has policies in it. Move them to another category first, then undo this.';
			}
			$kids = get_terms(array('taxonomy' => 'category', 'parent' => (int) $term->term_id, 'hide_empty' => false, 'fields' => 'ids'));
			if ($kids && !is_wp_error($kids)) {
				return 'It now has sub-categories inside it. Move or delete those first, then undo this.';
			}
			wp_delete_term($term->term_id, 'category');
			hr_chatbot_orders_restore($d['orders']);
			return array('message' => '"' . $e['title'] . '" has been removed again.');

		case 'cat_edit':
			$term = hr_chatbot_load_category($d['term_id']);
			if (!$term) {
				return 'That category no longer exists.';
			}
			$args = array();
			if (isset($d['name'])) {
				$args['name'] = wp_slash($d['name']);
			}
			if (isset($d['parent'])) {
				if ($d['parent'] && !hr_chatbot_load_category($d['parent'])) {
					return 'The category it was inside no longer exists, so I cannot move it back.';
				}
				$args['parent'] = (int) $d['parent'];
			}
			$res = wp_update_term($term->term_id, 'category', $args);
			if (is_wp_error($res)) {
				return hr_chatbot_term_error($res, isset($d['name']) ? $d['name'] : $term->name);
			}
			hr_chatbot_orders_restore($d['orders']);
			return array('message' => '"' . (isset($d['name']) ? $d['name'] : $e['title']) . '" is back the way it was.');

		case 'cat_order':
			if (!hr_chatbot_load_category($d['term_id'])) {
				return 'That category no longer exists.';
			}
			hr_chatbot_orders_restore($d['orders']);
			return array('message' => 'The menu order is back the way it was.');

		case 'cat_move':
			foreach ($d['posts'] as $p) {
				foreach ($p['before'] as $cat_id) {
					$t = get_term((int) $cat_id, 'category');
					if (!$t || is_wp_error($t)) {
						return 'A category those policies came from has been deleted since. Undo that deletion first, then undo this.';
					}
				}
			}
			$restored = 0;
			$skipped  = 0;
			foreach ($d['posts'] as $pid => $p) {
				$now   = array_map('intval', wp_get_post_categories($pid));
				$after = array_map('intval', $p['after']);
				sort($now);
				sort($after);
				if (!hr_chatbot_load_policy($pid) || $now !== $after) {
					$skipped++; // gone, or its category was changed again since
					continue;
				}
				wp_set_post_categories($pid, array_map('intval', $p['before']));
				$restored++;
			}
			if (!$restored) {
				return 'Those policies have been changed again since, so I did not move any of them back.';
			}
			return array('message' => $restored . ($restored === 1 ? ' policy is' : ' policies are') . ' back in "' . $e['title'] . '".' . ($skipped ? ' ' . $skipped . ' could not be moved back because it was changed again since.' : ''));

		case 'cat_delete':
			$k      = $d['kept'];
			$parent = (int) $k['parent'];
			if ($parent && !hr_chatbot_load_category($parent)) {
				return 'The category it was inside no longer exists, so I cannot bring it back there.';
			}
			$res = wp_insert_term(wp_slash($k['name']), 'category', array(
				'slug'        => $k['slug'],
				'parent'      => $parent,
				'description' => wp_slash($k['description']),
			));
			if (is_wp_error($res)) {
				return hr_chatbot_term_error($res, $k['name']);
			}
			$new = (int) $res['term_id'];
			if ($k['order'] !== null) {
				update_term_meta($new, 'order', $k['order']);
			}
			hr_chatbot_log_remap((int) $d['term_id'], $new); // it has a new id now
			return array('message' => '"' . $k['name'] . '" is back (' . strtolower($k['where']) . ').');

		case 'bulk':
			$restored = 0;
			foreach ($d['items'] as $pid => $prev) {
				$post = hr_chatbot_load_policy($pid);
				if (!$post) {
					continue; // removed since
				}
				if ($prev['is'] === null) {
					delete_post_meta($post->ID, 'is_archived');
				} else {
					update_post_meta($post->ID, 'is_archived', $prev['is']);
				}
				if ($prev['at'] === null) {
					delete_post_meta($post->ID, 'archived_at');
				} else {
					update_post_meta($post->ID, 'archived_at', $prev['at']);
				}
				if ($post->post_status !== $prev['status']) {
					wp_update_post(array('ID' => $post->ID, 'post_status' => $prev['status']));
				}
				$now = array_map('intval', wp_get_post_categories($post->ID));
				sort($now);
				$was = array_map('intval', $prev['cats']);
				sort($was);
				if ($now !== $was) {
					$valid = array_filter($was, function ($c) {
						return term_exists((int) $c, 'category');
					});
					wp_set_post_categories($post->ID, $valid ? $valid : array());
				}
				clean_post_cache($post->ID);
				$restored++;
			}
			if (!$restored) {
				return 'None of those policies exist any more, so there is nothing to put back.';
			}
			return array('message' => $restored . ($restored === 1 ? ' policy is' : ' policies are') . ' back the way ' . ($restored === 1 ? 'it was' : 'they were') . '.');
	}

	return 'I do not know how to undo that change.';
}

//----------------------------------------------------------------------------
// Policy health check: what is missing or out of date
//----------------------------------------------------------------------------

/** dd/mm/yyyy as a timestamp, or 0 when it is not a real date. */
function hr_chatbot_parse_date($s)
{
	if (!preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', trim((string) $s), $m) || !checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
		return 0;
	}
	return mktime(0, 0, 0, (int) $m[2], (int) $m[1], (int) $m[3]);
}

function hr_chatbot_task_health()
{
	$defs = array(
		'nofile'     => array('No policy file', 'Nothing is attached, so visitors have nothing to download.'),
		'nopdf'      => array('Word/Excel file without a PDF', 'The policy file is not a PDF and no PDF version was added.'),
		'stale'      => array('Policy date older than ' . HR_CHATBOT_STALE_MONTHS . ' months', 'Update the policy date if it is still current, or archive it.'),
		'nodate'     => array('No policy date', 'Visitors cannot tell when it was last updated.'),
		'nopurpose'  => array('No Purpose/Scope', 'The short description is empty.'),
		'nokeywords' => array('No keywords', 'Search by keyword will not find it.'),
		'nocat'      => array('No category', 'It is not listed in the menu.'),
	);

	$posts = get_posts(array(
		'post_type'     => 'post',
		'post_status'   => array('publish', 'private'),
		'numberposts'   => -1,
		'orderby'       => 'title',
		'order'         => 'ASC',
		'meta_query'    => hr_chatbot_archive_meta_query(false),
		'no_found_rows' => true,
	));

	$limit = strtotime('-' . HR_CHATBOT_STALE_MONTHS . ' months', time());
	$found = array_fill_keys(array_keys($defs), array());

	foreach ($posts as $post) {
		$id   = $post->ID;
		$file = (string) get_post_meta($id, 'policy_download_url', true);
		$pdf  = (string) get_post_meta($id, 'doc_to_pdf', true);
		$doc  = (int) get_post_meta($id, 'document_revisions_details', true);
		$date = (string) get_post_meta($id, 'post_policy_date', true);
		$ts   = hr_chatbot_parse_date($date);
		$cats = array_diff(array_map('intval', wp_get_post_categories($id)), array(1));

		if (!$file && !$doc) {
			$found['nofile'][] = $post;
		} elseif ($file && !$pdf && strtolower(pathinfo((string) wp_parse_url($file, PHP_URL_PATH), PATHINFO_EXTENSION)) !== 'pdf') {
			$found['nopdf'][] = $post;
		}
		if ($ts && $ts < $limit) {
			$found['stale'][] = $post;
		}
		if (!$ts) {
			$found['nodate'][] = $post;
		}
		if (trim($post->post_excerpt) === '') {
			$found['nopurpose'][] = $post;
		}
		if (!wp_get_post_tags($id, array('fields' => 'ids'))) {
			$found['nokeywords'][] = $post;
		}
		if (!$cats) {
			$found['nocat'][] = $post;
		}
	}

	$groups = array();
	foreach ($defs as $code => $def) {
		if (!$found[$code]) {
			continue;
		}
		$groups[] = array(
			'code'  => $code,
			'label' => $def[0],
			'hint'  => $def[1],
			'total' => count($found[$code]),
			'items' => array_map('hr_chatbot_format_policy', array_slice($found[$code], 0, 60)),
		);
	}

	hr_chatbot_reply(array('ok' => true, 'checked' => count($posts), 'groups' => $groups));
}

//----------------------------------------------------------------------------
// Bulk actions
//----------------------------------------------------------------------------

/** Same meta keys as the single archive task and the archive_post / restore_post handlers. */
function hr_chatbot_apply_archive($post_id, $archive)
{
	if ($archive) {
		update_post_meta($post_id, 'is_archived', 1);
		update_post_meta($post_id, 'archived_at', current_time('mysql'));
	} else {
		delete_post_meta($post_id, 'is_archived');
		delete_post_meta($post_id, 'archived_at');
	}
	clean_post_cache($post_id);
}

function hr_chatbot_task_bulk()
{
	$action = hr_chatbot_field('action');
	if (!in_array($action, array('archive', 'restore', 'public', 'private', 'move'), true)) {
		hr_chatbot_reply(array('ok' => false, 'code' => 'validation', 'message' => 'I do not know that bulk action.'));
	}

	$ids = array_values(array_unique(array_filter(array_map('absint', explode(',', hr_chatbot_field('ids'))))));
	if (!$ids) {
		hr_chatbot_reply(array('ok' => false, 'code' => 'validation', 'message' => 'Please choose at least one policy.'));
	}
	if (count($ids) > HR_CHATBOT_BULK_MAX) {
		hr_chatbot_reply(array('ok' => false, 'code' => 'validation', 'message' => 'Please choose at most ' . HR_CHATBOT_BULK_MAX . ' policies at a time.'));
	}

	$to = absint(hr_chatbot_field('to', 0));
	if ($action === 'move' && (!$to || $to === 1 || !term_exists($to, 'category'))) {
		hr_chatbot_reply(array('ok' => false, 'code' => 'validation', 'message' => 'Please choose a valid category.'));
	}

	$prev    = array();
	$titles  = array();
	$skipped = 0;
	foreach ($ids as $id) {
		$post = hr_chatbot_load_policy($id);
		if (!$post) {
			$skipped++;
			continue;
		}

		$state = array(
			'is'     => metadata_exists('post', $id, 'is_archived') ? get_post_meta($id, 'is_archived', true) : null,
			'at'     => metadata_exists('post', $id, 'archived_at') ? get_post_meta($id, 'archived_at', true) : null,
			'status' => $post->post_status,
			'cats'   => array_map('intval', wp_get_post_categories($id)),
		);

		$archived = (bool) $state['is'];
		$changed  = false;
		switch ($action) {
			case 'archive':
				if (!$archived) {
					hr_chatbot_apply_archive($id, true);
					$changed = true;
				}
				break;
			case 'restore':
				if ($archived) {
					hr_chatbot_apply_archive($id, false);
					$changed = true;
				}
				break;
			case 'public':
			case 'private':
				$want = $action === 'public' ? 'publish' : 'private';
				if ($post->post_status !== $want) {
					$res     = wp_update_post(array('ID' => $id, 'post_status' => $want), true);
					$changed = !is_wp_error($res);
				}
				break;
			case 'move':
				if ($state['cats'] !== array($to)) {
					wp_set_post_categories($id, array($to));
					$changed = true;
				}
				break;
		}

		if ($changed) {
			clean_post_cache($id);
			$prev[$id] = $state;
			$titles[]  = wp_specialchars_decode(get_the_title($id), ENT_QUOTES);
		} else {
			$skipped++;
		}
	}

	$n = count($prev);
	if ($n) {
		$to_name = $to ? hr_chatbot_term_path($to) : '';
		$text    = array(
			'archive' => array('Policies archived', 'Archived', 'Active'),
			'restore' => array('Policies restored from the archive', 'Active', 'Archived'),
			'public'  => array('Policies made public', 'Public', 'Private'),
			'private' => array('Policies made private', 'Private', 'Public'),
			'move'    => array('Policies moved to another category', 'In ' . $to_name, 'In their previous category'),
		);
		$list = implode(', ', array_slice($titles, 0, 4)) . ($n > 4 ? ' and ' . ($n - 4) . ' more' : '');
		hr_chatbot_log_add(
			'bulk',
			$text[$action][0],
			$n . ($n === 1 ? ' policy' : ' policies'),
			array(array('Policies', $text[$action][1] . ': ' . $list, $text[$action][2])),
			array('items' => $prev, 'action' => $action)
		);
	}

	$done = array(
		'archive' => 'archived',
		'restore' => 'restored',
		'public'  => 'made public',
		'private' => 'made private',
		'move'    => 'moved',
	);
	$message = $n
		? $n . ($n === 1 ? ' policy was ' : ' policies were ') . $done[$action] . '.'
		: 'Nothing needed changing. They were already like that.';
	if ($n && $skipped) {
		$message .= ' ' . $skipped . ($skipped === 1 ? ' was' : ' were') . ' left as it was (already like that, or no longer there).';
	}
	hr_chatbot_reply(array('ok' => true, 'message' => $message, 'done' => $n));
}

//----------------------------------------------------------------------------
// Export to CSV (the file itself is built in the browser from these rows)
//----------------------------------------------------------------------------

function hr_chatbot_task_export()
{
	$scope = hr_chatbot_field('scope', 'active');
	$args  = array(
		'post_type'     => 'post',
		'post_status'   => array('publish', 'private'),
		'numberposts'   => -1,
		'orderby'       => 'title',
		'order'         => 'ASC',
		'no_found_rows' => true,
	);
	if ($scope === 'active') {
		$args['meta_query'] = hr_chatbot_archive_meta_query(false);
	} elseif ($scope === 'archived') {
		$args['meta_query'] = hr_chatbot_archive_meta_query(true);
	}

	$rows = array();
	foreach (get_posts($args) as $post) {
		$p      = hr_chatbot_format_policy($post);
		$rows[] = array(
			$p['title'],
			$p['category'],
			$p['status'] === 'private' ? 'Private' : 'Public',
			$p['archived'] ? 'Archived' : 'Active',
			$p['date'],
			$p['keywords'],
			wp_specialchars_decode($p['purpose'], ENT_QUOTES),
			$p['file'],
			$p['pdf'],
			$p['url'],
		);
	}

	hr_chatbot_reply(array(
		'ok'     => true,
		'header' => array('Title', 'Category', 'Access', 'Status', 'Policy date', 'Keywords', 'Purpose/Scope', 'Policy file', 'PDF', 'Link'),
		'rows'   => $rows,
	));
}

//----------------------------------------------------------------------------
// Scheduled archiving (runs through WP-Cron, so it happens on the first page load after the time)
//----------------------------------------------------------------------------

define('HR_CHATBOT_SCHEDULE_HOOK', 'hr_chatbot_run_schedule');
define('HR_CHATBOT_SCHEDULE_META', '_hr_chatbot_schedule');

add_action(HR_CHATBOT_SCHEDULE_HOOK, 'hr_chatbot_run_schedule', 10, 2);
function hr_chatbot_run_schedule($post_id, $at)
{
	$s = get_post_meta($post_id, HR_CHATBOT_SCHEDULE_META, true);
	if (!is_array($s) || (int) $s['at'] !== (int) $at) {
		return; // cancelled or replaced by a newer date
	}
	delete_post_meta($post_id, HR_CHATBOT_SCHEDULE_META);

	$post = hr_chatbot_load_policy($post_id);
	if (!$post || get_post_meta($post_id, 'is_archived', true)) {
		return;
	}

	$was = array(
		'is' => metadata_exists('post', $post_id, 'is_archived') ? get_post_meta($post_id, 'is_archived', true) : null,
		'at' => metadata_exists('post', $post_id, 'archived_at') ? get_post_meta($post_id, 'archived_at', true) : null,
	);
	hr_chatbot_apply_archive($post_id, true);
	hr_chatbot_log_add(
		'archive',
		'Policy archived on schedule',
		wp_specialchars_decode(get_the_title($post_id), ENT_QUOTES),
		array(array('Status', 'Archived', 'Active')),
		array('post_id' => (int) $post_id, 'was' => $was),
		'Scheduled archive'
	);
}

function hr_chatbot_clear_schedule($post_id)
{
	$s = get_post_meta($post_id, HR_CHATBOT_SCHEDULE_META, true);
	if (is_array($s) && !empty($s['at'])) {
		wp_unschedule_event((int) $s['at'], HR_CHATBOT_SCHEDULE_HOOK, array((int) $post_id, (int) $s['at']));
	}
	delete_post_meta($post_id, HR_CHATBOT_SCHEDULE_META);
}

function hr_chatbot_task_schedule()
{
	$post = hr_chatbot_load_policy(absint(hr_chatbot_field('id', 0)));
	if (!$post) {
		hr_chatbot_reply(array('ok' => false, 'code' => 'notfound', 'message' => 'I could not find that policy.'));
	}
	if (get_post_meta($post->ID, 'is_archived', true)) {
		hr_chatbot_reply(array('ok' => false, 'code' => 'validation', 'message' => 'That policy is already archived.'));
	}

	$date = hr_chatbot_field('date');
	$ts   = hr_chatbot_parse_date($date);
	if (!$ts) {
		hr_chatbot_reply(array('ok' => false, 'code' => 'validation', 'message' => 'Please give a real date in dd/mm/yyyy format.'));
	}

	// Just after midnight, site time, on that date.
	$when = (int) get_gmt_from_date(date('Y-m-d', $ts) . ' 00:05:00', 'U');
	if ($when <= time() + 300) {
		hr_chatbot_reply(array('ok' => false, 'code' => 'validation', 'message' => 'Please choose a date in the future (tomorrow or later).'));
	}

	hr_chatbot_clear_schedule($post->ID);
	update_post_meta($post->ID, HR_CHATBOT_SCHEDULE_META, array('at' => $when, 'by' => wp_get_current_user()->display_name));
	wp_schedule_single_event($when, HR_CHATBOT_SCHEDULE_HOOK, array((int) $post->ID, $when));

	hr_chatbot_reply(array('ok' => true, 'message' => '"' . wp_specialchars_decode(get_the_title($post->ID), ENT_QUOTES) . '" will be archived on ' . $date . '.', 'when' => $date));
}

function hr_chatbot_task_schedules()
{
	$posts = get_posts(array(
		'post_type'     => 'post',
		'post_status'   => array('publish', 'private'),
		'numberposts'   => -1,
		'meta_key'      => HR_CHATBOT_SCHEDULE_META,
		'no_found_rows' => true,
	));

	$items = array();
	foreach ($posts as $post) {
		$s = get_post_meta($post->ID, HR_CHATBOT_SCHEDULE_META, true);
		if (!is_array($s) || empty($s['at'])) {
			continue;
		}
		$items[] = array(
			'policy' => hr_chatbot_format_policy($post),
			'at'     => (int) $s['at'],
			'when'   => wp_date('d/m/Y', (int) $s['at']),
			'by'     => isset($s['by']) ? (string) $s['by'] : '',
		);
	}
	usort($items, function ($a, $b) {
		return $a['at'] - $b['at'];
	});

	hr_chatbot_reply(array('ok' => true, 'items' => $items));
}

function hr_chatbot_task_unschedule()
{
	$post = hr_chatbot_load_policy(absint(hr_chatbot_field('id', 0)));
	if (!$post) {
		hr_chatbot_reply(array('ok' => false, 'code' => 'notfound', 'message' => 'I could not find that policy.'));
	}
	hr_chatbot_clear_schedule($post->ID);
	hr_chatbot_reply(array('ok' => true, 'message' => 'The scheduled archive for "' . wp_specialchars_decode(get_the_title($post->ID), ENT_QUOTES) . '" has been cancelled.'));
}

//----------------------------------------------------------------------------
// Searches that found nothing: what visitors look for that no policy covers
//----------------------------------------------------------------------------

define('HR_CHATBOT_GAPS_OPTION', 'hr_chatbot_gaps');

// Only the search words are kept (no user, no IP), and searches by policy admins are ignored.
add_action('wp', 'hr_chatbot_record_search_gap');
function hr_chatbot_record_search_gap()
{
	if (is_admin() || !is_search() || is_paged() || !empty($_POST['hr_chatbot'])) {
		return;
	}
	global $wp_query;
	if (!$wp_query || $wp_query->found_posts > 0 || hr_user_can_manage_policies()) {
		return;
	}

	hr_chatbot_gap_add((string) get_query_var('s'));
}

/** Remembers a search / question that found nothing (only the words, no user). */
function hr_chatbot_gap_add($q)
{
	$q = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags((string) $q)));
	$q = function_exists('mb_strtolower') ? mb_strtolower($q) : strtolower($q);
	if (strlen($q) < 2 || strlen($q) > 80) {
		return;
	}

	$list = get_option(HR_CHATBOT_GAPS_OPTION, array());
	if (!is_array($list)) {
		$list = array();
	}
	$key = md5($q);
	if (isset($list[$key])) {
		$list[$key]['n']++;
		$list[$key]['last'] = time();
	} else {
		$list[$key] = array('q' => $q, 'n' => 1, 'last' => time());
	}

	if (count($list) > HR_CHATBOT_GAPS_KEEP) { // drop the least searched, oldest ones
		uasort($list, function ($a, $b) {
			return $a['n'] === $b['n'] ? $a['last'] - $b['last'] : $a['n'] - $b['n'];
		});
		$list = array_slice($list, count($list) - HR_CHATBOT_GAPS_KEEP, null, true);
	}
	update_option(HR_CHATBOT_GAPS_OPTION, $list, false);
}

function hr_chatbot_task_gaps($clear)
{
	if ($clear) {
		delete_option(HR_CHATBOT_GAPS_OPTION);
		hr_chatbot_reply(array('ok' => true, 'message' => 'The list has been cleared.', 'items' => array()));
	}

	$list = get_option(HR_CHATBOT_GAPS_OPTION, array());
	$list = is_array($list) ? array_values($list) : array();
	usort($list, function ($a, $b) {
		return $a['n'] === $b['n'] ? $b['last'] - $a['last'] : $b['n'] - $a['n'];
	});

	$items = array();
	foreach (array_slice($list, 0, 15) as $g) {
		$items[] = array('q' => (string) $g['q'], 'n' => (int) $g['n'], 'when' => human_time_diff($g['last']) . ' ago');
	}
	hr_chatbot_reply(array('ok' => true, 'items' => $items, 'total' => count($list)));
}

//----------------------------------------------------------------------------
// Words: shared by employee questions and the upload autofill
//----------------------------------------------------------------------------

function hr_chatbot_lower($s)
{
	return function_exists('mb_strtolower') ? mb_strtolower((string) $s) : strtolower((string) $s);
}

function hr_chatbot_stopwords()
{
	return array(
		'a', 'about', 'above', 'after', 'all', 'also', 'am', 'an', 'and', 'any', 'are', 'as', 'at', 'be', 'been', 'but', 'by', 'can', 'could',
		'did', 'do', 'does', 'for', 'from', 'get', 'give', 'got', 'had', 'has', 'have', 'how', 'i', 'if', 'in', 'into', 'is', 'it', 'its',
		'know', 'look', 'looking', 'may', 'me', 'must', 'my', 'need', 'no', 'not', 'of', 'on', 'or', 'our', 'out', 'please', 'shall',
		'should', 'show', 'such', 'tell', 'than', 'that', 'the', 'their', 'them', 'then', 'there', 'these', 'they', 'this', 'those', 'to',
		'under', 'us', 'want', 'was', 'we', 'were', 'what', 'when', 'where', 'which', 'who', 'will', 'with', 'within', 'would', 'you', 'your',
		'find', 'policy', 'policies', 'employee', 'employees', 'company', 'section', 'page', 'including', 'other', 'each', 'than',
		'one', 'ones', 'many', 'much', 'day', 'days', 'explain', 'info', 'information', 'details', 'detail',
	);
}

/** Meaningful lower-case words of a text; a trailing plural "s" is dropped so "leaves" matches "leave". */
function hr_chatbot_tokens($text)
{
	$parts = preg_split('/[^\p{L}\p{N}]+/u', hr_chatbot_lower($text), -1, PREG_SPLIT_NO_EMPTY);
	if (!$parts) {
		return array();
	}
	$stop = array_flip(hr_chatbot_stopwords());
	$out  = array();
	foreach ($parts as $w) {
		if (isset($stop[$w]) || strlen($w) < 3) {
			continue;
		}
		if (strlen($w) > 4 && substr($w, -1) === 's' && substr($w, -2) !== 'ss') {
			$w = substr($w, 0, -1);
		}
		$out[$w] = $w;
	}
	return array_values($out);
}

/** Everyday words people use for the same thing, so "WFH" finds the remote work policy. */
function hr_chatbot_synonyms()
{
	return array(
		'leave'      => array('vacation', 'holiday', 'pto', 'absence', 'time off'),
		'vacation'   => array('leave', 'holiday', 'pto'),
		'holiday'    => array('leave', 'vacation'),
		'pto'        => array('leave', 'vacation', 'holiday'),
		'salary'     => array('pay', 'payroll', 'compensation', 'wage'),
		'pay'        => array('salary', 'payroll', 'compensation'),
		'payroll'    => array('salary', 'pay', 'compensation'),
		'wfh'        => array('remote', 'work from home', 'hybrid'),
		'remote'     => array('wfh', 'work from home', 'hybrid'),
		'sick'       => array('medical', 'illness', 'health'),
		'medical'    => array('sick', 'health', 'insurance'),
		'insurance'  => array('medical', 'mediclaim', 'health'),
		'travel'     => array('trip', 'journey', 'tour', 'reimbursement'),
		'expense'    => array('reimbursement', 'claim'),
		'claim'      => array('expense', 'reimbursement'),
		'harassment' => array('posh', 'conduct', 'grievance'),
		'resign'     => array('resignation', 'exit', 'separation', 'notice'),
		'resignation' => array('resign', 'exit', 'separation', 'notice'),
		'exit'       => array('resignation', 'separation', 'notice'),
		'maternity'  => array('paternity', 'parental'),
		'paternity'  => array('maternity', 'parental'),
		'laptop'     => array('asset', 'device', 'equipment'),
		'dress'      => array('attire', 'uniform'),
		'hike'       => array('increment', 'appraisal', 'promotion'),
		'appraisal'  => array('performance', 'review', 'increment'),
	);
}

/**
 * How well a policy answers the question. $fields is [weight => lower-case text]; a word counts once, for the
 * heaviest field it appears in, and a synonym counts for 60% of that.
 */
function hr_chatbot_relevance($tokens, $fields, &$cover = null)
{
	// A word matches where a word of the text starts with it ("leave" finds "leaves", but "one" does not find "someone").
	$has = function ($text, $w) {
		return (bool) preg_match('/(?<![\p{L}\p{N}])' . preg_quote($w, '/') . '/u', $text);
	};

	$syn   = hr_chatbot_synonyms();
	$score = 0.0;
	$hit   = 0;
	foreach ($tokens as $t) {
		$best = 0.0;
		foreach ($fields as $weight => $text) {
			if ($text === '' || $weight <= $best) {
				continue;
			}
			if ($has($text, $t)) {
				$best = $weight;
				continue;
			}
			if (isset($syn[$t])) {
				foreach ($syn[$t] as $s) {
					if ($has($text, $s)) {
						$best = max($best, $weight * 0.6);
						break;
					}
				}
			}
		}
		if ($best > 0) {
			$score += $best;
			$hit++;
		}
	}
	$cover = $tokens ? $hit / count($tokens) : 0.0; // the share of the question's words that were found
	return $tokens ? $score + 3 * $cover : 0.0;
}

//----------------------------------------------------------------------------
// Employee questions: "how many days of leave do I get?" -> the matching policies
//----------------------------------------------------------------------------

/** Policy admins (and roles with "admin" in the name, as the site's own search treats them) also see private policies. */
function hr_chatbot_sees_private()
{
	if (hr_user_can_manage_policies()) {
		return true;
	}
	foreach ((array) wp_get_current_user()->roles as $role) {
		if (strpos($role, 'admin') !== false) {
			return true;
		}
	}
	return false;
}

/** At most 30 questions a minute per person. */
function hr_chatbot_rate_limited()
{
	$key = 'hr_chatbot_rl_' . get_current_user_id();
	$d   = get_transient($key);
	if (!is_array($d) || time() - $d[1] > 60) {
		$d = array(0, time());
	}
	if ($d[0] >= 30) {
		return true;
	}
	$d[0]++;
	set_transient($key, $d, 120);
	return false;
}

/** A downloadable link, or '' when the stored value is not a web address. */
function hr_chatbot_file_url($post_id, $key)
{
	$url = (string) get_post_meta($post_id, $key, true);
	return preg_match('#^(https?:)?//#i', $url) || strpos($url, '/') === 0 ? esc_url_raw($url) : '';
}

function hr_chatbot_task_ask()
{
	if (hr_chatbot_rate_limited()) {
		hr_chatbot_reply(array('ok' => false, 'code' => 'toomany', 'message' => 'You are asking very quickly. Please wait a minute and try again.'));
	}

	$q   = substr(hr_chatbot_field('q'), 0, 200);
	$cat = absint(hr_chatbot_field('cat', 0));

	$args = array(
		'post_type'     => 'post',
		'post_status'   => hr_chatbot_sees_private() ? array('publish', 'private') : array('publish'),
		'numberposts'   => -1,
		'orderby'       => 'title',
		'order'         => 'ASC',
		'meta_query'    => hr_chatbot_archive_meta_query(false),
		'no_found_rows' => true,
	);

	$answer = function ($post) {
		$p             = hr_chatbot_format_policy($post);
		$p['file_url'] = hr_chatbot_file_url($post->ID, 'policy_download_url');
		$p['pdf_url']  = hr_chatbot_file_url($post->ID, 'doc_to_pdf');
		return $p;
	};

	// Browse one category (and everything inside it).
	if ($cat) {
		$term = hr_chatbot_load_category($cat);
		if (!$term) {
			hr_chatbot_reply(array('ok' => false, 'code' => 'notfound', 'message' => 'I could not find that category.'));
		}
		$kids                 = get_term_children($term->term_id, 'category');
		$args['category__in'] = array_merge(array((int) $term->term_id), is_wp_error($kids) ? array() : array_map('intval', $kids));
		$posts                = get_posts($args);
		hr_chatbot_reply(array(
			'ok'       => true,
			'category' => wp_specialchars_decode($term->name, ENT_QUOTES),
			'items'    => array_map($answer, array_slice($posts, 0, HR_CHATBOT_ASK_MAX)),
			'total'    => count($posts),
		));
	}

	$tokens = hr_chatbot_tokens($q);
	if (!$tokens) {
		hr_chatbot_reply(array('ok' => false, 'code' => 'validation', 'message' => 'Please ask with a few more words, for example "maternity leave" or "how do I claim travel expenses".'));
	}

	$ranked = array();
	foreach (get_posts($args) as $post) {
		$cats = array();
		foreach (get_the_category($post->ID) as $c) {
			$cats[] = $c->name;
		}
		$cover = 0.0;
		$score = hr_chatbot_relevance($tokens, array(
			5 => hr_chatbot_lower(get_the_title($post->ID)),
			4 => hr_chatbot_lower(implode(' ', wp_get_post_tags($post->ID, array('fields' => 'names')))),
			3 => hr_chatbot_lower(implode(' ', $cats)),
			2 => hr_chatbot_lower($post->post_excerpt),
			1 => hr_chatbot_lower(basename((string) get_post_meta($post->ID, 'policy_download_url', true))),
		), $cover);
		if ($score >= 3.5) { // at least one real word match (the bonus alone is at most 3)
			$ranked[] = array($score, $post, $cover);
		}
	}
	usort($ranked, function ($a, $b) {
		return $a[0] < $b[0] ? 1 : ($a[0] > $b[0] ? -1 : strcasecmp($a[1]->post_title, $b[1]->post_title));
	});

	// When some policies match well, the ones that match far less are noise: drop them.
	if ($ranked) {
		$floor  = $ranked[0][0] * 0.5;
		$ranked = array_values(array_filter($ranked, function ($r) use ($floor) {
			return $r[0] >= $floor;
		}));
	}

	if (!$ranked && !hr_user_can_manage_policies()) {
		hr_chatbot_gap_add($q); // lets the HR team see what people look for and cannot find
	}

	// Every match is returned (up to HR_CHATBOT_ASK_MAX); the chat shows a few and offers "Show more".
	$top = array_slice($ranked, 0, HR_CHATBOT_ASK_MAX);
	hr_chatbot_reply(array(
		'ok'    => true,
		'words' => $tokens,
		'weak'  => $ranked && $ranked[0][2] < 0.6, // fewer than 60% of the question's words were found
		'items' => array_map(function ($r) use ($answer) {
			return $answer($r[1]);
		}, $top),
		'total' => count($ranked),
	));
}

//----------------------------------------------------------------------------
// Upload-to-autofill: read a policy file on this server and suggest its details.
// Nothing is sent to an outside service and the file is not stored by this step.
//----------------------------------------------------------------------------

/** Plain text of a PDF / DOCX / CSV. '' when the file cannot be read (scanned PDF, old .doc, spreadsheet ...). */
function hr_chatbot_extract_text($path, $ext, &$title)
{
	$title = '';
	$text  = '';
	try {
		if ($ext === 'pdf') {
			$autoload = __DIR__ . '/vendor/autoload.php';
			if (is_readable($autoload)) {
				require_once $autoload;
				$parser = new \Smalot\PdfParser\Parser();
				$pdf    = $parser->parseFile($path);
				$text   = (string) $pdf->getText();
				$info   = $pdf->getDetails();
				if (!empty($info['Title']) && is_string($info['Title'])) {
					$title = $info['Title'];
				}
			}
		} elseif ($ext === 'docx') {
			$zip = new ZipArchive();
			if ($zip->open($path) === true) {
				$stat = $zip->statName('word/document.xml');
				if ($stat && $stat['size'] <= 20 * 1024 * 1024) {
					$xml  = (string) $zip->getFromName('word/document.xml');
					$xml  = preg_replace(array('#</w:p>#', '#<w:br\s*/?>#', '#<w:tab\s*/?>#'), array("\n", "\n", ' '), $xml);
					$text = html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
				}
				$core = (string) $zip->getFromName('docProps/core.xml');
				if (preg_match('#<dc:title>(.*?)</dc:title>#s', $core, $m)) {
					$title = html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_XML1, 'UTF-8');
				}
				$zip->close();
			}
		} elseif ($ext === 'csv') {
			$text = (string) file_get_contents($path, false, null, 0, 20000);
		}
	} catch (\Throwable $e) {
		$text = '';
	}

	$text = wp_check_invalid_utf8($text, true);
	return function_exists('mb_substr') ? mb_substr($text, 0, 200000) : substr($text, 0, 200000);
}

function hr_chatbot_trim_words($s, $max)
{
	$words = preg_split('/\s+/u', trim($s), -1, PREG_SPLIT_NO_EMPTY);
	if (!$words) {
		return '';
	}
	if (count($words) <= $max) {
		return implode(' ', $words);
	}
	$cut = implode(' ', array_slice($words, 0, $max));
	$end = max((int) strrpos($cut, '. '), (int) strrpos($cut, '.' . "\n"));
	return $end > strlen($cut) * 0.5 ? substr($cut, 0, $end + 1) : rtrim($cut, ',;:') . '...';
}

/** dd/mm/yyyy found at the start of $s (dd/mm/yyyy, dd-mm-yyyy, "12 March 2026" or "March 12, 2026"), or ''. */
function hr_chatbot_find_date($s)
{
	$months = array('jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6, 'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12);
	$d = $m = $y = 0;
	if (preg_match('#(\d{1,2})[/.\-](\d{1,2})[/.\-](\d{4})#', $s, $x)) {
		list(, $d, $m, $y) = $x;
	} elseif (preg_match('#(\d{1,2})(?:st|nd|rd|th)?\s+([A-Za-z]{3,9})\.?,?\s+(\d{4})#', $s, $x) && isset($months[strtolower(substr($x[2], 0, 3))])) {
		list(, $d, , $y) = $x;
		$m = $months[strtolower(substr($x[2], 0, 3))];
	} elseif (preg_match('#([A-Za-z]{3,9})\.?\s+(\d{1,2})(?:st|nd|rd|th)?,?\s+(\d{4})#', $s, $x) && isset($months[strtolower(substr($x[1], 0, 3))])) {
		list(, , $d, $y) = $x;
		$m = $months[strtolower(substr($x[1], 0, 3))];
	}
	return ($d && checkdate((int) $m, (int) $d, (int) $y)) ? sprintf('%02d/%02d/%04d', $d, $m, $y) : '';
}

/** Everything the assistant can guess from the text and the file name. Empty values mean "no suggestion". */
function hr_chatbot_suggest($text, $filename, $doc_title)
{
	$lines = array();
	foreach (preg_split('/\R/u', $text) as $l) {
		$l = trim(preg_replace('/\s+/u', ' ', $l));
		if ($l !== '') {
			$lines[] = $l;
		}
	}
	$wc = function ($s) {
		return count(preg_split('/\s+/u', trim($s), -1, PREG_SPLIT_NO_EMPTY));
	};

	// Name: the document's own title, else a heading line that says "policy" / "procedure" ..., else the file name.
	$title = '';
	$bad   = '/^(microsoft word|untitled|document\d*|new document|presentation)/i';
	if ($doc_title !== '' && strlen($doc_title) <= 100 && !preg_match($bad, $doc_title)) {
		$title = $doc_title;
	}
	if ($title === '') {
		foreach (array_slice($lines, 0, 15) as $l) {
			if (strlen($l) >= 4 && strlen($l) <= 90 && $wc($l) <= 12 && preg_match('/\b(policy|procedure|guidelines?|code|standard|handbook|rules|framework|charter)\b/i', $l)) {
				$title = $l;
				break;
			}
		}
	}
	if ($title === '') {
		$base  = pathinfo($filename, PATHINFO_FILENAME);
		$base  = preg_replace('/[_\-.]+/', ' ', $base);
		$base  = preg_replace('/\b(v|ver|version)\s*\d+(\s*\d+)*\b|\bfinal\b|\bcopy\b|\bdraft\b|\(\d+\)|\b\d{1,2}\s?\d{1,2}\s?\d{2,4}\b/i', ' ', $base);
		$title = trim(preg_replace('/\s+/', ' ', $base));
		if ($title !== '' && $title === hr_chatbot_lower($title)) {
			$title = ucwords($title);
		}
	}
	$title = trim(preg_replace('/\s+/u', ' ', $title));

	// Purpose/Scope: the text under a Purpose / Scope / Objective / Overview heading, else the first real paragraph.
	$heading = '/^(?:\d+(?:\.\d+)*[.)]?\s*)?(purpose(?:\s*(?:and|&)\s*scope)?|scope|objectives?|introduction|overview)\s*(?:[:\-–—]\s*(.*)|)$/iu';
	$is_head = function ($l) use ($wc) {
		return ($wc($l) <= 6 && !preg_match('/[.!?]$/', $l)) || preg_match('/^\d+(\.\d+)*[.)]?\s+\p{Lu}/u', $l) && $wc($l) <= 8;
	};
	$purpose = '';
	foreach ($lines as $i => $l) {
		if (!preg_match($heading, $l, $m)) {
			continue;
		}
		$body = isset($m[2]) ? trim($m[2]) : '';
		for ($j = $i + 1; $j < count($lines) && $j < $i + 8 && $wc($body) < 25; $j++) {
			if ($is_head($lines[$j])) {
				break;
			}
			$body .= ' ' . $lines[$j];
		}
		if ($wc($body) >= 5) {
			$purpose = $body;
			break;
		}
	}
	if ($purpose === '') {
		foreach (array_slice($lines, 1, 40) as $l) {
			if ($wc($l) >= 14 && !preg_match($heading, $l)) {
				$purpose = $l;
				break;
			}
		}
	}
	$purpose = hr_chatbot_trim_words($purpose, 50);

	// Policy date: next to words like "effective", "last updated", "revised", "issued".
	$date = '';
	$head = function_exists('mb_substr') ? mb_substr($text, 0, 8000) : substr($text, 0, 8000);
	if (preg_match_all('/(effective|last\s+(?:updated|revised|reviewed)|revised|issued|version\s+date|date\s+of\s+issue|updated)\b[^\n]{0,60}/iu', $head, $hits)) {
		foreach ($hits[0] as $seg) {
			$date = hr_chatbot_find_date($seg);
			if ($date) {
				break;
			}
		}
	}

	// Keywords: the commonest meaningful words, with the name's words counting extra.
	$freq = array();
	foreach (hr_chatbot_tokens(function_exists('mb_substr') ? mb_substr($text, 0, 20000) : substr($text, 0, 20000)) as $w) {
		$freq[$w] = 0;
	}
	$sample = hr_chatbot_lower(function_exists('mb_substr') ? mb_substr($text, 0, 20000) : substr($text, 0, 20000));
	foreach (array_keys($freq) as $w) {
		$freq[$w] = substr_count($sample, $w);
	}
	$in_title = hr_chatbot_tokens($title);
	foreach ($in_title as $w) {
		$freq[$w] = (isset($freq[$w]) ? $freq[$w] : 0) + 5;
	}
	$freq = array_filter($freq, function ($n, $w) {
		return strlen($w) >= 4 && !is_numeric($w) && $n >= 2;
	}, ARRAY_FILTER_USE_BOTH);
	arsort($freq);
	$keywords = implode(', ', array_slice(array_keys($freq), 0, 6));

	// Category: the one whose name appears most in the name and the opening of the text.
	$cat_id   = 0;
	$cat_name = '';
	$best     = 0;
	$opening  = hr_chatbot_lower(function_exists('mb_substr') ? mb_substr($text, 0, 3000) : substr($text, 0, 3000));
	$all      = get_categories(array('hide_empty' => false, 'exclude' => 1));
	$by_id    = array();
	foreach ($all as $t) {
		$by_id[$t->term_id] = $t;
	}
	foreach ($all as $t) {
		$score = 0;
		foreach (hr_chatbot_tokens($t->name) as $w) {
			$score += (strpos(hr_chatbot_lower($title), $w) !== false ? 3 : 0) + min(5, substr_count($opening, $w));
		}
		if ($score > $best) {
			$best     = $score;
			$cat_id   = (int) $t->term_id;
			$cat_name = hr_chatbot_category_path($t, $by_id);
		}
	}
	if ($best < 3) {
		$cat_id   = 0;
		$cat_name = '';
	}

	return array(
		'title'    => $title,
		'purpose'  => $purpose,
		'keywords' => $keywords,
		'date'     => $date,
		'cat'      => $cat_id,
		'cat_name' => $cat_name,
	);
}

function hr_chatbot_task_analyze()
{
	@set_time_limit(60);

	$f = isset($_FILES['file']) ? $_FILES['file'] : null;
	if (!$f || $f['error'] === UPLOAD_ERR_NO_FILE) {
		hr_chatbot_reply(array('ok' => false, 'code' => 'validation', 'message' => 'Please attach the policy file.'));
	}
	if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) {
		hr_chatbot_reply(array('ok' => false, 'code' => 'validation', 'message' => 'The file is larger than the server allows (' . size_format(wp_max_upload_size()) . ').'));
	}
	if ($f['error'] !== UPLOAD_ERR_OK) {
		hr_chatbot_reply(array('ok' => false, 'code' => 'validation', 'message' => 'The file did not upload completely (error ' . (int) $f['error'] . '). Please try again.'));
	}
	$ok = validate_policy_file($f);
	if ($ok !== true) {
		hr_chatbot_reply(array('ok' => false, 'code' => 'validation', 'message' => $ok));
	}

	$name = sanitize_file_name(wp_unslash($f['name']));
	$ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
	$text = hr_chatbot_extract_text($f['tmp_name'], $ext, $doc_title);

	hr_chatbot_reply(array(
		'ok'         => true,
		'read'       => trim($text) !== '',
		'suggestion' => hr_chatbot_suggest($text, $name, $doc_title),
	));
}

//----------------------------------------------------------------------------
// Policy history: the files uploaded for a policy, and putting an earlier one back.
//
// Every upload becomes an attachment of the policy's document. What visitors get is decided by the
// policy's policy_download_url / doc_to_pdf meta and the document's "<!-- WPDR id -->" content, which
// is exactly what the Edit Policy page sets after an upload. Restoring points them at an older upload;
// no file is copied or deleted, so the file that was showing stays stored and can be restored again.
//----------------------------------------------------------------------------

/** Site-relative path stored in the policy meta (the theme prepends site_url() when showing it). */
function hr_chatbot_meta_path($url)
{
	return str_replace(site_url(), '', $url);
}

/**
 * Uploads of a policy's document, newest first, as one entry per upload (a Word/Excel file and its PDF
 * come together). Files that are gone from disk are left out, as they cannot be restored.
 *
 * @return array [ [main => file, pdf => file|null, ts, by, current], ... ]; a file is [id, name, ext, url, size]
 */
function hr_chatbot_version_groups($post_id)
{
	$doc_id = (int) get_post_meta($post_id, 'document_revisions_details', true);
	if (!$doc_id || get_post_type($doc_id) !== 'document') {
		return array();
	}

	$attachments = get_posts(array(
		'post_parent'   => $doc_id,
		'post_type'     => 'attachment',
		'post_status'   => 'inherit',
		'numberposts'   => -1,
		'orderby'       => 'ID',
		'order'         => 'DESC',
		'no_found_rows' => true,
	));

	// Built from the stored file path, not wp_get_attachment_url(): WP Document Revisions rewrites that to a
	// revision link for older files.
	$base   = wp_upload_dir();
	$base   = $base['baseurl'];
	$groups = array();
	foreach ($attachments as $a) {
		$rel = (string) get_post_meta($a->ID, '_wp_attached_file', true);
		$abs = get_attached_file($a->ID);
		if ($rel === '' || !$abs || !file_exists($abs)) {
			continue;
		}
		$file = array(
			'id'   => (int) $a->ID,
			'name' => basename($rel),
			'ext'  => strtolower(pathinfo($rel, PATHINFO_EXTENSION)),
			'url'  => $base . '/' . $rel,
			'size' => (int) filesize($abs),
		);
		$ts   = (int) strtotime($a->post_date_gmt . ' UTC');

		// Files uploaded by the same save are seconds apart.
		$last = count($groups) - 1;
		if ($last >= 0 && abs($groups[$last]['ts'] - $ts) <= 120 && $groups[$last]['by'] === (int) $a->post_author) {
			$groups[$last]['files'][] = $file;
		} else {
			$groups[] = array('ts' => $ts, 'by' => (int) $a->post_author, 'files' => array($file));
		}
	}

	$current = (string) get_post_meta($post_id, 'policy_download_url', true);
	$out     = array();
	foreach ($groups as $g) {
		$files = array_reverse($g['files']); // oldest first: the main upload, then its PDF
		$main  = null;
		$pdf   = null;
		foreach ($files as $f) {
			if ($f['ext'] !== 'pdf' && !$main) {
				$main = $f;
			}
			if ($f['ext'] === 'pdf') {
				$pdf = $f;
			}
		}
		if (!$main) {
			$main = $pdf; // a PDF on its own is both
			$pdf  = null;
		}
		$user  = get_userdata($g['by']);
		$out[] = array(
			'main'    => $main,
			'pdf'     => $pdf,
			'ts'      => $g['ts'],
			'by'      => $user ? $user->display_name : '',
			'current' => $current !== '' && hr_chatbot_meta_path($main['url']) === $current,
		);
	}

	if ($out && $current === '') {
		$out[0]['current'] = true; // no meta: the policy page shows the newest file
	}
	return $out;
}

function hr_chatbot_task_versions()
{
	$post = hr_chatbot_load_policy(absint(hr_chatbot_field('id', 0)));
	if (!$post) {
		hr_chatbot_reply(array('ok' => false, 'code' => 'notfound', 'message' => 'I could not find that policy.'));
	}

	$groups   = hr_chatbot_version_groups($post->ID);
	$versions = array();
	foreach (array_slice($groups, 0, 15) as $g) {
		$versions[] = array(
			'key'     => $g['main']['id'],
			'when'    => wp_date('d/m/Y H:i', $g['ts']),
			'by'      => $g['by'],
			'main'    => array('name' => $g['main']['name'], 'ext' => $g['main']['ext'], 'url' => $g['main']['url'], 'size' => size_format($g['main']['size'])),
			'pdf'     => $g['pdf'] ? array('name' => $g['pdf']['name'], 'url' => $g['pdf']['url'], 'size' => size_format($g['pdf']['size'])) : null,
			'current' => $g['current'],
		);
	}

	hr_chatbot_reply(array(
		'ok'       => true,
		'policy'   => hr_chatbot_format_policy($post),
		'versions' => $versions,
		'total'    => count($groups),
	));
}

/** Saves how a policy looks now so the change can be undone. Returns the list of snapshots. */
function hr_chatbot_undo_push($post_id)
{
	$user   = wp_get_current_user();
	$list   = hr_chatbot_undo_list($post_id);
	$list[] = array(
		'id'    => wp_generate_password(8, false),
		'time'  => time(),
		'user'  => $user->ID,
		'name'  => $user->display_name,
		'state' => hr_chatbot_capture($post_id),
	);
	$list = array_slice($list, -HR_CHATBOT_UNDO_KEEP);
	hr_chatbot_undo_save($post_id, $list);
	return $list;
}

function hr_chatbot_task_version_restore()
{
	$post = hr_chatbot_load_policy(absint(hr_chatbot_field('id', 0)));
	if (!$post) {
		hr_chatbot_reply(array('ok' => false, 'code' => 'notfound', 'message' => 'I could not find that policy.'));
	}

	$key   = absint(hr_chatbot_field('version', 0));
	$found = null;
	foreach (hr_chatbot_version_groups($post->ID) as $g) {
		if ($g['main']['id'] === $key) {
			$found = $g;
			break;
		}
	}
	if (!$found) {
		hr_chatbot_reply(array('ok' => false, 'code' => 'notfound', 'message' => 'That file is no longer available. Open the history again to see what is stored.'));
	}
	if ($found['current']) {
		hr_chatbot_reply(array('ok' => false, 'code' => 'validation', 'message' => 'That is already the file shown on the policy.'));
	}

	$before = hr_chatbot_capture($post->ID);
	$list   = hr_chatbot_undo_push($post->ID);

	$doc_id     = (int) get_post_meta($post->ID, 'document_revisions_details', true);
	$main       = $found['main'];
	$pdf        = $found['pdf'] ? $found['pdf'] : ($main['ext'] === 'pdf' ? $main : null);
	$content_id = $found['pdf'] ? $found['pdf']['id'] : $main['id']; // the last file of an upload is the document's file

	update_post_meta($post->ID, 'policy_download_url', hr_chatbot_meta_path($main['url']));
	if ($pdf) {
		update_post_meta($post->ID, 'doc_to_pdf', hr_chatbot_meta_path($pdf['url']));
	} else {
		delete_post_meta($post->ID, 'doc_to_pdf'); // that upload had no PDF: do not keep showing another version's
	}
	wp_update_post(array('ID' => $doc_id, 'post_content' => '<!-- WPDR ' . $content_id . ' -->'));
	update_post_meta($doc_id, '_wpdr_document', $content_id);

	wp_cache_delete($doc_id, 'document_revisions');
	hr_chatbot_clean_caches($post->ID);

	$changed = hr_chatbot_capture($post->ID) !== $before;
	if (!$changed) {
		array_pop($list);
		hr_chatbot_undo_save($post->ID, $list);
	}

	hr_chatbot_reply(array(
		'ok'      => true,
		'message' => 'The policy now shows the file uploaded on ' . wp_date('d/m/Y H:i', $found['ts']) . '.',
		'policy'  => hr_chatbot_format_policy(get_post($post->ID)),
		'undo'    => $changed,
	));
}
