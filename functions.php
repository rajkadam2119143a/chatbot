<?php

/*
 * This is the child theme for Policies theme, generated with Generate Child Theme plugin by catchthemes.
 *
 * (Please see https://developer.wordpress.org/themes/advanced-topics/child-themes/#how-to-create-a-child-theme)
 */

add_action('wp_enqueue_scripts', 'policies_child_enqueue_styles');
function policies_child_enqueue_styles()
{
	wp_enqueue_style('parent-style', get_template_directory_uri() . '/style.css');
	wp_enqueue_style(
		'child-style',
		get_stylesheet_directory_uri() . '/style.css',
		array('parent-style')
	);
}

/*
 * Your code goes below
 */
require_once get_stylesheet_directory() . '/custom/api/class-api-authentication.php';
require_once get_stylesheet_directory() . '/custom/api/class-api-response.php';
require_once(ABSPATH . 'wp-admin/includes/image.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/media.php');

/**
 * Load Custom Comments Layout file.
 */
require get_stylesheet_directory() . '/comments-helper.php';

/**
 * Policy Assistant chatbot (add / edit / search / archive policies by chat).
 */
require_once get_stylesheet_directory() . '/custom/chatbot/policy-chatbot.php';

// function my_shortcode($atts, $content = null, $tag = '')
// {
//  ob_start();
//  print_r($content);
//  set_query_var('attributes', $atts);
//  get_template_part('includes/latest', 'policies');
//  return ob_get_clean();
// }
// add_shortcode('latest_policies', 'my_shortcode');

function filename_rewrite($file)
{
	$extension = pathinfo($file['name'], PATHINFO_EXTENSION);

	$file['name'] = md5($file['name'] . microtime()) . '.' . $extension;

	return $file;
}

function document_upload_dir_filter($dir)
{
	global $wpdr;
	$wpdr_dir = $wpdr->document_upload_dir();
	$wpdr_url = '/' . $wpdr->document_slug();

	$dir['path']    = $wpdr_dir . $dir['subdir'];
	$dir['url']     = home_url($wpdr_url) . $dir['subdir'];
	$dir['basedir'] = $wpdr_dir;
	$dir['baseurl'] = home_url($wpdr_url);
	return $dir;
}

global $doc_id;

/**
 * Hide the name in the URL.
 *
 * @param array $file file object from WP.
 * @return array modified file array
 */
function upload_rewrite_url($file)
{

	global $doc_id;
	$file['url'] = get_permalink($doc_id);

	return $file;
}


/**
 * Register Custom Navigation Walker
 */
function register_navwalker()
{
	require_once get_template_directory() . '/class-wp-bootstrap-navwalker.php';
}
add_action('after_setup_theme', 'register_navwalker');

// Load Stylesheets
function load_css()
{
	wp_register_style('bootstrap', get_stylesheet_directory_uri() . '/css/bootstrap.min.css', array(), false, 'all');
	wp_enqueue_style('bootstrap');

	wp_register_style('custom-styles', get_stylesheet_directory_uri() . '/css/custom_styles.css', array(), false, 'all');
	wp_enqueue_style('custom-styles');

	wp_register_style('font-awesome', get_stylesheet_directory_uri() . '/css/font-awesome.min.css', array(), false, 'all');
	wp_enqueue_style('font-awesome');

	wp_register_style('owl-carousel', get_stylesheet_directory_uri() . '/css/owl.carousel.min.css', array(), false, 'all');
	wp_enqueue_style('owl-carousel');

	wp_register_style('owl-theme-default', get_stylesheet_directory_uri() . '/css/owl.theme.default.min.css', array(), false, 'all');
	wp_enqueue_style('owl-theme-default');

	wp_register_style(
		'jquery-ui-search',
		get_stylesheet_directory_uri() . '/css/jquery-ui.css',
		array(),
		false,
		'all'
	);
	wp_enqueue_style('jquery-ui-search');

	wp_register_style(
		'bootstrap-datepicker',
		'https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.min.css',
		array(),
		false,
		'all'
	);
	wp_enqueue_style('bootstrap-datepicker');
}
add_action('wp_enqueue_scripts', 'load_css');

$str = rand();
$result = md5($str);
$_SESSION['nonce'] = $result;
// Load Javascript
function load_js()
{
	wp_enqueue_script('jquery');

	wp_register_script('bootstrap', get_stylesheet_directory_uri() . '/js/bootstrap.min.js', 'jquery', false, true);
	wp_enqueue_script('bootstrap');


	wp_register_script('custom_scripts', get_stylesheet_directory_uri() . '/js/custom_scripts.js', 'jquery', false, true);
	wp_enqueue_script('custom_scripts');


	wp_register_script('owl-carousel', get_stylesheet_directory_uri() . '/js/owl.carousel.min.js', 'jquery', false, true);
	wp_enqueue_script('owl-carousel');



	wp_localize_script('custom_scripts', 'my_ajax_object', array(
		'ajax_url' => admin_url('admin-ajax.php'),
		//'ajax_nonce' => $_SESSION['nonce']
	));

	wp_enqueue_script(
		'jquery-ui-search',
		get_stylesheet_directory_uri() . '/js/jquery-ui.js',
		['jquery', 'jquery-ui-autocomplete'],
		null,
		true
	);

	wp_enqueue_script(
		'autocomplete-search',
		get_stylesheet_directory_uri() . '/js/autocomplete.js',
		['jquery', 'jquery-ui-autocomplete'],
		null,
		true
	);

	wp_register_script('bootstrap-datepicker', 'https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js', 'jquery', false, true);
	wp_enqueue_script('bootstrap-datepicker');
}
add_action('wp_enqueue_scripts', 'load_js');


//Users Access
function ui_new_role()
{

	add_role(
		'subscriber',
		'subscriber',
		array(
			'read'         => true,
			'read_documents' => true,
			'read_document_revisions' => true,
			'read_private_documents' => true,
			'read_private_posts' => true,
			'read_private_pages' => true,
		)
	);
}
//add_action('init', 'ui_new_role');

// function ws_private_posts_subscribers(){
//   global $current_user;
//   $current_user = wp_get_current_user();
//   $user_roles = $current_user->roles;
//   //print_r($user_roles);
//   if(check_admin() ==  true || current_user_can('subscriber-it')) {
//     $current_user->add_cap( 'read_private_posts' );
//     $current_user->add_cap( 'read_private_pages' );
//   }
// }
//add_action( 'template_redirect', 'ws_private_posts_subscribers' );

//Super Admin Access
function ui_new_admin_role()
{

	add_role(
		'policy-admin',
		'policy-admin',
		array(
			'read'         => true,
			'delete_posts' => true,
			'edit_posts'   => true,
			'publish_post' => true,
			'upload_files' => true,
			'read_documents' => true,
			'read_document_revisions' => true,
			'read_private_documents' => true,
			'read_private_posts' => true,
			'read_private_pages' => true,
		)
	);
}
//add_action('init', 'ui_new_admin_role');

function ui_new_admin_role_subscriber_mas()
{

	add_role(
		'subscriber-mas',
		'subscriber-mas',
		array(
			'read'         => true,
			'delete_posts' => true,
			'edit_posts'   => true,
			'publish_post' => true,
			'upload_files' => true,
			'read_documents' => true,
			'read_document_revisions' => true,
			'read_private_documents' => true,
			'read_private_posts' => true,
			'read_private_pages' => true,
		)
	);
}
add_action('init', 'ui_new_admin_role_subscriber_mas');

// Many people have two WP accounts: an old <name>@timesgroup.com login and a newer
// <name>@timesofindia.com login (the old one's email was also changed to timesofindia.com).
// email_exists() returns whichever comes first (the old, role-less one), so a policy-admin
// was logged in as the wrong account. Pick the account that has the most access on this site.
function hr_resolve_login_user_id($email)
{
    global $wpdb;

    $email = strtolower(trim((string) $email));
    if ($email === '' || strpos($email, '@') === false) {
        return false;
    }
    $local = substr($email, 0, strpos($email, '@'));

    $logins = array($email, $local . '@timesgroup.com', $local . '@timesofindia.com');
    $ph     = implode(',', array_fill(0, count($logins), '%s'));
    $ids    = $wpdb->get_col($wpdb->prepare(
        "SELECT ID FROM {$wpdb->users} WHERE user_email = %s OR user_login IN ($ph) ORDER BY ID ASC",
        array_merge(array($email), $logins)
    ));

    if (empty($ids)) {
        return false;
    }

    $best_id    = false;
    $best_score = -1;
    foreach ($ids as $id) {
        $user = get_userdata((int) $id);
        if (!$user) {
            continue;
        }
        $score = 0;
        if (!empty($user->roles)) {
            $score = 1;
        }
        if (user_can($user, 'edit_posts') || array_intersect((array) $user->roles, array('administrator', 'policy-admin'))) {
            $score = 3;
        }
        if (strtolower($user->user_login) === $email) {
            $score += 0.5;
        }
        if ($score > $best_score) {
            $best_score = $score;
            $best_id    = (int) $id;
        }
    }

    return $best_id;
}

function check_login_user()
{
    $all_policy_admin = explode(',', ALL_ADMIN);

    // ✅ Whitelisted email list
    $whitelisted_users = array(
        'c-laxmi.akula@timesofindia.com',
        'mohd.bilal@timesofindia.com',
        'rekha.handa@timesofindia.com',
        'swati.koli@timesofindia.com',
        'vattackanayil.jacob@timesofindia.com',
        'c-khushbu.jaiswal@timesofindia.com',
        'c-dipti.maini@timesofindia.com',
        'c-anuradha.hardwani@timesofindia.com',
        'rubina.khan@timesofindia.com',
        'bijoya.chanda@timesofindia.com',
        'dipali.anerao@timesofindia.com',
    );

    if (!is_user_logged_in() && $GLOBALS['pagenow'] != 'wp-login.php' && !is_admin()) {

        // Cookie login (unchanged flow)
        if (!empty($_COOKIE['i_s']) && (empty($_SERVER['PHP_AUTH_USER']) && empty($_SERVER['PHP_AUTH_PW']))) {
            $apiResponse = new apiResponse();
            $api_response = $apiResponse->curlresponse();

            if ($api_response['resp'] == 'true') {
                if ($user_id = hr_resolve_login_user_id($api_response['email'])) {
                    wp_set_current_user($user_id);
                    $my_user = wp_get_current_user();

                    if (in_array($user_id, $all_policy_admin)) {
                        $my_user->set_role('policy-admin');
                        wp_update_user(array('ID' => $user_id, 'role' => 'policy-admin'));
                    }

                    wp_update_user(array('ID' => $user_id, 'first_name' => $api_response['first_name'], 'last_name' => $api_response['last_name']));
                    update_user_meta($user_id, 'branch', $api_response['branch']);
                    update_user_meta($user_id, 'location', $api_response['location']);
                    update_user_meta($user_id, 'designation', $api_response['designation']);
                    update_user_meta($user_id, 'department', $api_response['department']);
                    update_user_meta($user_id, 'sap', $api_response['sap']);
                    if (isset($api_response['profile_picture'])) {
                        update_user_meta($user_id, 'profile_picture', $api_response['profile_picture']);
                    }
                    if (isset($api_response['manager_email'])) {
                        update_user_meta($user_id, 'manager_email', $api_response['manager_email']);
                    }
                } else {
                    $user_id = wp_create_user(strtolower($api_response['email']), "", $api_response['email']);
                    if (!is_wp_error($user_id)) {
                        wp_set_current_user($user_id);
                        $my_user = wp_get_current_user();
                        if (in_array($user_id, $all_policy_admin)) {
                            $my_user->set_role('policy-admin');
                            wp_update_user(array('ID' => $user_id, 'role' => 'policy-admin'));
                        }
                        wp_insert_user(array('ID' => $user_id, 'first_name' => $api_response['first_name'], 'last_name' => $api_response['last_name']));
                        add_user_meta($user_id, 'branch', $api_response['branch']);
                        add_user_meta($user_id, 'location', $api_response['location']);
                        add_user_meta($user_id, 'designation', $api_response['designation']);
                        add_user_meta($user_id, 'department', $api_response['department']);
                        add_user_meta($user_id, 'sap', $api_response['sap']);
                        if (isset($api_response['profile_picture'])) {
                            add_user_meta($user_id, 'profile_picture', $api_response['profile_picture']);
                        }
                        if (isset($api_response['manager_email'])) {
                            add_user_meta($user_id, 'manager_email', $api_response['manager_email']);
                        }
                    } else {
                        error_log('function.php: Login based on cookie: Error creating user -' . $user_id);
                    }
                }
            } else {
                header('WWW-Authenticate: Basic realm="Authentication Required"');
                header('HTTP/1.0 401 Unauthorized');
                exit;
            }
        } else {
            // 🔹 Basic Auth section
            if (!empty($_SERVER['PHP_AUTH_USER']) || !empty($_SERVER['PHP_AUTH_PW'])) {
                $username = strtolower(trim($_SERVER['PHP_AUTH_USER']));

                // ✅ Check if email is whitelisted
                if (in_array($username, array_map('strtolower', $whitelisted_users))) {
                    if ($user_id = hr_resolve_login_user_id($username)) {
                        // Already exists → log in
                        wp_set_current_user($user_id);
                        wp_set_auth_cookie($user_id, true);
                        return;
                    } else {
                        // Create new WP user directly (skip API)
                        $user_id = wp_create_user($username, wp_generate_password(), $username);
                        if (!is_wp_error($user_id)) {
                            wp_set_current_user($user_id);
                            wp_set_auth_cookie($user_id, true);
                            return;
                        }
                    }
                }

                // 🔹 Non-whitelisted → normal API flow
                $apiAuthentication = new apiAuthentication();
                $user_info = $apiAuthentication->curlrequest($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);

                if ($user_id = hr_resolve_login_user_id($user_info['email'])) {
                    wp_set_current_user($user_id);
                    $my_user = wp_get_current_user();

                    if (in_array($user_id, $all_policy_admin)) {
                        $my_user->set_role('policy-admin');
                        wp_update_user(array('ID' => $user_id, 'role' => 'policy-admin'));
                    }

                    wp_update_user(array('ID' => $user_id, 'first_name' => $user_info['first_name'], 'last_name' => $user_info['last_name']));
                    update_user_meta($user_id, 'branch', $user_info['branch']);
                    update_user_meta($user_id, 'location', $user_info['location']);
                    update_user_meta($user_id, 'designation', $user_info['designation']);
                    update_user_meta($user_id, 'department', $user_info['department']);
                    update_user_meta($user_id, 'sap', $user_info['sap']);
                    if (isset($user_info['profile_picture'])) {
                        update_user_meta($user_id, 'profile_picture', $user_info['profile_picture']);
                    }
                    if (isset($user_info['manager_email'])) {
                        update_user_meta($user_id, 'manager_email', $user_info['manager_email']);
                    }

                    setcookie('i_s', $user_info['i_s'],  $user_info['i_s_expiry'], "/", ".timesgroup.com", true, true);
                } else {
                    if (filter_var($user_info['email'], FILTER_VALIDATE_EMAIL)) {
                        $email = $user_info['email'];
                        $user_id = wp_create_user(strtolower($email), "", $email);
                        if (!is_wp_error($user_id)) {
                            wp_set_current_user($user_id);
                            $my_user = wp_get_current_user();
                            if (in_array($user_id, $all_policy_admin)) {
                                $my_user->set_role('policy-admin');
                                wp_update_user(array('ID' => $user_id, 'role' => 'policy-admin'));
                            }
                            wp_insert_user(array('ID' => $user_id, 'first_name' => $user_info['first_name'], 'last_name' => $user_info['last_name']));
                            add_user_meta($user_id, 'branch', $user_info['branch']);
                            add_user_meta($user_id, 'location', $user_info['location']);
                            add_user_meta($user_id, 'designation', $user_info['designation']);
                            add_user_meta($user_id, 'department', $user_info['department']);
                            add_user_meta($user_id, 'sap', $user_info['sap']);
                            if (isset($user_info['profile_picture'])) {
                                add_user_meta($user_id, 'profile_picture', $user_info['profile_picture']);
                            }
                            if (isset($user_info['manager_email'])) {
                                add_user_meta($user_id, 'manager_email', $user_info['manager_email']);
                            }
                        } else {
                            error_log('function.php: Error creating user -' . $_SERVER['PHP_AUTH_USER']);
                        }
                        setcookie('i_s', $user_info['i_s'],  $user_info['i_s_expiry'], "/", ".timesgroup.com", true, true);
                    } else {
                        error_log('Email not valid for-' . $_SERVER['PHP_AUTH_USER']);
                        header('WWW-Authenticate: Basic realm="Authentication Required"');
                        exit;
                    }
                }
            } else {
                header('WWW-Authenticate: Basic realm="Authentication Required"');
                echo '<script>window.close();</script>';
                exit;
            }
        }
    }
}
add_action('init', 'check_login_user', 10);

add_action('after_setup_theme', 'remove_admin_bar');

function remove_admin_bar()
{
	if (!current_user_can('subscriber') && !is_admin()) {
		show_admin_bar(false);
	}

	if (current_user_can('subscriber')) {
		global $current_user;
		$current_user = wp_get_current_user();
		$current_user->add_cap('level_0');
		$current_user->add_cap('read');
		$current_user->add_cap('read_documents');
		$current_user->add_cap('read_document_revisions');
		$current_user->add_cap('read_private_documents');
		$current_user->add_cap('read_private_posts');
		$current_user->add_cap('read_private_pages');
	}
}

//-------------------------------------------------------------------------------

//Custom Admin Sidebar

add_action('widgets_init', 'my_awesome_sidebar');
function my_awesome_sidebar()
{
	$args = array(
		'name'          => 'Admin Sidebar',
		'id'            => 'admin-sidebar',
		'description'   => 'The Admin Sidebar is shown on the left hand side of admin pages in this theme',
		'class'         => '',
		'before_widget' => '<div class="col-md-3 paddingDiv"><div class="bg-sidebar">',
		'after_widget'  => '</div></div>',
		'before_title'  => '<div id="reply-title" class="comment-reply-title">',
		'after_title'   => '</div><div class="BorderTop"></div>'
	);

	register_sidebar($args);
}

add_filter('widget_tag_cloud_args', 'all_tag_cloud_widget_parameters');
function all_tag_cloud_widget_parameters()
{
	$args = array(
		'smallest' => 12,
		'largest' => 18,
		'unit' => 'pt',
		'number' => 10,
		'format' => 'list',
		'separator' => "\n",
		'orderby' => 'name',
		'order' => 'ASC',
		'exclude' => '',
		'include' => '',
		'link' => 'view',
		'post_type' => 'post',
		'echo' => false
	);
	return $args;
}


function get_cat_icon($id, $key = '')
{
	global $wpdb;

	// Validate and sanitize inputs
	$id = intval($id);
	$key = sanitize_text_field($key);

	// Use a prepared statement for the query
	$strTable = $wpdb->prefix . 'termmeta';
	$icon_id = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT meta_value FROM $strTable WHERE term_id = %d AND meta_key = %s",
			$id,
			$key
		)
	);

	// Get the attachment image source
	if ($icon_id) {
		$src = wp_get_attachment_image_src($icon_id, "thumbnail");
		return $src ? $src[0] : ''; // Return the URL or an empty string if not found
	}

	return ''; // Return an empty string if no icon ID is found
}

//-----------------------------------------------------------------------------

function register_my_session()
{
	if (!session_id()) {
		session_start();
	}
}

add_action('init', 'register_my_session');

function category_has_parent($catid)
{
	$category = get_category($catid);
	if ($category->category_parent > 0) {
		return true;
	}
	return false;
}

//This file is use for add edit policies for admin
 include('common-admin-function.php');

function get_post_id_by_name($the_slug, $post_type = 'post')
{
	$post_ids = get_posts(array(
		'name'        => $the_slug,
		'post_type'   => $post_type,
		'post_status' => array('publish', 'private'),
		'numberposts' => 1
	));
	//print_r($post_ids);
	return array_shift($post_ids);
}

//Search by post title
add_filter('posts_where', 'qirolab_posts_where', 10, 2);
function qirolab_posts_where($where, $wp_query)
{
	global $wpdb;
	if ($title = $wp_query->get('search_title')) {
		$where .= " AND " . $wpdb->posts . ".post_title LIKE '%" . esc_sql($wpdb->esc_like($title)) . "%'";
	}
	return $where;
}

//----------------------------Autocomplete Search for Edit Policy------------------------------

add_action('wp_ajax_nopriv_autocompleteSearch', 'awp_autocomplete_search');
add_action('wp_ajax_autocompleteSearch', 'awp_autocomplete_search');
function awp_autocomplete_search()
{
	//check_ajax_referer('autocompleteSearchNonce', 'security');
	//echo "ajax_request---".$_REQUEST['security'];
	//echo "Session nonce---".$_SESSION['nonce'];
	// if($_REQUEST['security'] !== $_SESSION['nonce'])
	//   return;

	$search_term = $_REQUEST['term'];
	if (!isset($_REQUEST['term'])) {
		echo json_encode([]);
	}
	$suggestions = [];
	$args = array('exclude' => 1, 'fields' => 'ids');
	$exclude_uncategorized = get_terms('category', $args);
	$query = new WP_Query([
		'search_title' => $search_term,
		'posts_per_page' => -1,
		'post_type' => 'post',
		'post_status' => array('publish', 'private'),
		'category__in' => $exclude_uncategorized,
		'meta_query' => array(
			'relation' => 'OR',
			array(
				'key' => 'is_archived',
				'compare' => 'NOT EXISTS',
			),
			array(
				'key' => 'is_archived',
				'value' => '1',
				'compare' => '!=',
			),
		),
	]);
	if ($query->have_posts()) {
		while ($query->have_posts()) {
			$query->the_post();
			$post_content = get_the_content();

			$category = get_the_category();
			foreach ($category as $cat) {
				$cat = $cat->term_id;
			}

			//$post_excerpt = get_the_excerpt();
			$post_excerpt = wpse69204_excerpt(60, '.', get_the_ID());

			$post_id = get_the_ID();
			$post_data = get_post_meta($post_id, 'document_revisions_details');
			$doc_id = $post_data[0];

			$attachments = get_document_revisions($doc_id);

			$archive_html = '';
			if (!empty($attachments)) {
				$archive_html .= '<div class="bg-sidebar"><span class="sidebar-title-postHeading">Related Files</span><div class="BorderBottom"> </div><div class="policySectionHeight scrollbar"><div class="sidebar-menu-post1"><div class="menu-home-page-menu-container"><div class="panel-group" id="accordion"><div class="panel-default"><ul class="">';
				foreach ($attachments as $key => $value) {
					if ($value->post_status !== 'publish' && $value->post_excerpt != '') {
						$file_url = ('attachment' === get_post_type($value->ID)) ? wp_get_attachment_url($value->ID) : get_permalink($value->ID);
						$archive_html .= "<li><a target='_blank' href='" . esc_url($file_url) . "' download>" . esc_html($value->post_title) . "</a>";
						$archive_html .= '<p class="ReviewBoardSecsubtext">' . strtoupper(get_the_date(get_option('date_format'), $value->ID)) . '</p>';
						$archive_html .= "</li>";
					}
				}
				$archive_html .= '</ul></div></div></div></div></div></div>';
			}

			if ($archive_html != '') {
				$suggestions[] = [
					'add_data' => get_post_field('post_name', get_the_ID()), //slug
					'label' => html_entity_decode(get_the_title()),
					'link' => get_the_permalink(),
					'post_category' => $cat,
					'post_tags' => get_the_tags(),
					'post_excerpt' => html_entity_decode($post_excerpt),
					'archive_html' => $archive_html
				];
			} else {
				$suggestions[] = [
					'add_data' => get_post_field('post_name', get_the_ID()), //slug
					'label' => html_entity_decode(get_the_title()),
					'link' => get_the_permalink(),
					'post_category' => $cat,
					'post_tags' => get_the_tags(),
					'post_excerpt' => html_entity_decode($post_excerpt)
				];
			}
		}
		wp_reset_postdata();
	}
	echo json_encode($suggestions);
	wp_die();
}

//----------------------------Display raw excerpt on Edit Policy page------------------------

function display_default_excerpt($excerpt)
{

	if (has_excerpt()) {
		return $excerpt;
	} else {
		return __('');
	}
}
add_filter('the_excerpt', 'display_default_excerpt');

//Trim words for post excerpt

function wpse69204_excerpt($num_words = 60, $ending = '...', $post_id = null)
{
	global $post;

	// Truncate post excerpt
	$current_post = $post_id ? get_post($post_id) : $post;
	$excerpt = strip_shortcodes($current_post->post_excerpt);
	$excerpt = wp_trim_words($excerpt, $num_words, $ending);

	return $excerpt;
}

//--------------------------------Search policy----------------------------------------------

add_shortcode('wpshout_frontend_post_search', 'wpshout_frontend_post_search');
function wpshout_frontend_post_search()
{
	//wpshout_search_post_if_submitted();
?>
<form id="new_post" name="new_post" method="post">
	<?php
	$str = rand();
	$result = md5($str);

	$_SESSION['hidden_admin'] = $result;

	?>
	<input type="hidden" value="<?php echo $result; ?>" name="hidden-1">

	<div class="row formarginTop">
		<div class="col-md-6">
			<label for="title" class="formLabel">Name of Policy</label>
			<input type="text" class="form-control" id="title" size="20" name="title" />
		</div>
		<div class="col-md-6 MarginTopSecMobile">
			<label for="category" class="formLabel">Category</label>
			<?php wp_dropdown_categories(array(
		'taxonomy' => 'category',
		'hide_empty' => 0,
		'hierarchical' => 1,
		'show_option_none' => "Select",
		'option_none_value' => "",
		'name' => 'cat',
		'id' => 'cat_search',
		'class' => 'form-control selectControl',
		'value_field' => 'term_id',
		'exclude'  => 1
	));
			?>
		</div>
	</div>

	<div class="row formarginTop">
		<div class="col-md-6">
			<label for="post_tags" class="formLabel">Keywords</label>
			<textarea class="form-control-textarea textareClass" id="post_tags" name="post_tags" cols="50" rows="4"></textarea>
			<p><i>Comma separated values only.</i></p>
		</div>
		<div class="col-md-6 MarginTopSecMobile">
			<label for="post_excerpt" class="formLabel">Purpose/Scope</label>
			<textarea class="form-control-textarea textareClass" id="post_excerpt" name="post_excerpt" cols="50" rows="4"></textarea>
		</div>
	</div>

	<?php //wp_nonce_field( 'wps-frontend-post-search' ); 
	?>

	<div class="read-more-box formarginTop marginBottomSec">
		<button type="submit" class="btnCss" value="Search" id="submit" name="submit">
			<span class="except-more">
				Search <img src="<?php echo get_stylesheet_directory_uri(); ?>/images/redArrow.png" class="Redarraowimg">
			</span>
		</button>

	</div>

</form>
<?php

}


function post_pagination($paged = '', $max_page = '')
{
	if (!$paged) {
		//$paged = (get_query_var('paged')) ? get_query_var('paged') : ((get_query_var('page')) ? get_query_var('page') : 1);
		$paged = (get_query_var('paged') == 0) ? 1 : get_query_var('paged');
	}

	if (!$max_page) {
		global $wp_query;
		$max_page = isset($wp_query->max_num_pages) ? $wp_query->max_num_pages : 1;
	}

	$big  = 999999999; // need an unlikely integer

	$pages = paginate_links(array(
		'base'       => str_replace($big, '%#%', esc_url(get_pagenum_link($big))),
		'format'     => '?paged=%#%',
		'current'    => max(1, $paged),
		'total'      => $max_page,
		'type'       => 'array',
		'mid_size'   => 1,
		'prev_text'  => __('Prev'),
		'next_text'  => __('Next'),
	));


	if (is_array($pages)) {
		echo "<div class='tg-grid-area-bottom1 paginationWrapper'><div class='tg-pagination-holder'><ul class='tg-pagination-number'>";
		$count = 0;
		foreach ($pages as $page) {
			echo '<li class="tg-page"><span class="tg-page-number tg-page-ajax tg-nav-color tg-nav-border tg-nav-font tg-page-current" data-page="' . $count . '">' . $page . '</span></li>';
			$count++;
		}
		echo '</ul></div></div>';
	}
}

//---------------

// function modify_product_cat_query( $query ) {
//   if (get_query_var('paged')){
//     $query->set('posts_per_page', 20);
//   }
// }
// add_action( 'pre_get_posts', 'modify_product_cat_query' );

function customize_query_var_on_archive_using_toolset($query)
{
	if (!is_admin() && is_date() || !is_admin() && is_category()) {
		$query->query_vars['posts_per_page'] = 10;
	}

	if ($query->is_search) {
		$query->query_vars['post_type'] = 'post';

		if (check_admin() == true) {
			$query->query_vars['post_status'] = array('publish', 'private');
		} else {
			$query->query_vars['post_status'] = array('publish');
			$query->query_vars['meta_query'] = array(
				'relation' => 'OR',
				array(
					'key' => 'is_archived',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key' => 'is_archived',
					'value' => '1',
					'compare' => '!=',
				),
			);
		}
	}
	//error_log(print_r($query, true)); 
}
add_action('pre_get_posts', 'customize_query_var_on_archive_using_toolset');

//----------------------------------

add_action('wp_ajax_nopriv_delete_post', 'delete_post');
add_action('wp_ajax_delete_post', 'delete_post');
function delete_post()
{
	if (!hr_user_can_manage_policies() || empty($_REQUEST['slug'])) {
		echo 0;
		die;
	}

	$slug = $_REQUEST['slug'];

	$post_details = get_post_id_by_name(wp_strip_all_tags($slug), 'post');
	$post_id = $post_details->ID;

	$my_post = array(
		'ID' =>  $post_id,
		'post_status'  => 'private',
	);
	$update_status = wp_update_post($my_post);

	if ($update_status != 0) {
		echo 1;
	} else {
		echo 0;
	}

	die;
}

add_action('wp_ajax_archive_post', 'archive_post');
function archive_post()
{
	if (!hr_user_can_manage_policies() || empty($_REQUEST['slug'])) {
		echo 0;
		die;
	}

	$slug = $_REQUEST['slug'];
	$post_details = get_post_id_by_name(wp_strip_all_tags($slug), 'post');
	if (!$post_details) {
		echo 0;
		die;
	}

	$post_id = $post_details->ID;
	update_post_meta($post_id, 'is_archived', 1);
	update_post_meta($post_id, 'archived_at', current_time('mysql'));

	echo 1;
	die;
}

add_action('wp_ajax_restore_post', 'restore_post');
function restore_post()
{
	if (!hr_user_can_manage_policies() || empty($_REQUEST['slug'])) {
		echo 0;
		die;
	}

	$slug = $_REQUEST['slug'];
	$post_details = get_post_id_by_name(wp_strip_all_tags($slug), 'post');
	if (!$post_details) {
		echo 0;
		die;
	}

	$post_id = $post_details->ID;
	delete_post_meta($post_id, 'is_archived');
	delete_post_meta($post_id, 'archived_at');

	echo 1;
	die;
}



add_filter('get_archives_link', function ($link_html, $url, $text, $format, $before, $after) {

	if ('custom' == $format) {
		$link_html = "\t<option value=\"" . esc_attr($text) . "\" >$before$text$after</option>\n";
	}

	return $link_html;
}, 10, 6);


//----------------

//Check admin role
function check_admin()
{
	global $current_user;
	$user_roles = (array) $current_user->roles;
	foreach ($user_roles as $role) {
		if (str_contains($role, "admin")) {
			return true;
		}
	}
	return false;
}

// Who may add / edit / archive / delete policies: super admin, administrator,
// policy-admin or any other role with edit_posts. Checked by role name as well as
// capability, so it still works if a role's capabilities were stripped on a site.
function hr_user_can_manage_policies()
{
	if (!is_user_logged_in()) {
		return false;
	}
	if (is_super_admin() || current_user_can('edit_posts')) {
		return true;
	}
	$user = wp_get_current_user();
	return (bool) array_intersect((array) $user->roles, array('administrator', 'policy-admin'));
}

function global_notice_meta_box()
{

	add_meta_box(
		'global-notice',
		__('Document ID', 'sitepoint'),
		'global_notice_meta_box_callback',
		'post'
	);
}

function global_notice_meta_box_callback($post)
{

	// Add a nonce field so we can check for it later.
	wp_nonce_field('global_notice_nonce', 'global_notice_nonce');

	$value = get_post_meta($post->ID, 'document_revisions_details', true);

	echo '<input style="width:100%" id="global_notice" name="document_revisions_details" value="' . esc_attr($value) . '">';
}

add_action('add_meta_boxes', 'global_notice_meta_box');

// Allow subscribers to view private policies.

//Site search query
//add_action('pre_get_posts','filter_search');
// function filter_Search($query){
//     //if( is_admin() || ! $query->is_main_query() ) return;
//     if ($query->is_search) {
//         if(current_user_can("subscriber-it") || check_admin() == true) {
//             $query->set('post_type','post');
//             $query->set('post_status',array('private','publish'));
//             $query->set('category__not_in',array(1)); 
//         }else {
//             $query->set('post_type','post');
//             $query->set('post_status',array('publish'));
//             $query->set('category__not_in',array(1)); 
//             //$query->set('category__not_in',array(1,4,5,6)); 
//         }
//     }
// }

// Remove "Private: " from titles
function remove_private_prefix($title)
{
	$title = str_replace('Private: ', '', $title);
	return $title;
}
add_filter('the_title', 'remove_private_prefix');


// Show or hide the edit link on comments if you are not Administrator
function show_single_edit_comments($link, $comment_id, $text)
{
	if (is_user_logged_in()) {
		$comment_email = get_comment_author_email($comment_id);
		$email = wp_get_current_user()->user_email;

		if (current_user_can('administrator')) {
			return $link;
		} else {
			return false;
		}
	}

	return false;
}

add_filter('edit_comment_link', 'show_single_edit_comments', 10, 3);

add_filter('comment_reply_link', '__return_false');

//-------------------------

// add_action('template_redirect','yoursite_template_redirect');
// function yoursite_template_redirect() {
//   $useragent = $_SERVER['HTTP_USER_AGENT'];

//   //if (strpos($useragent, 'Macintosh') !== false) {
//       header('Content-Description: File Transfer');
//       header('Content-Type: application/pdf');
//       header('Content-Disposition: inline; filename=software-license-policy-and-process-revision-1.pdf');
//       header('Content-Transfer-Encoding: binary');
//       header('Expires: 0');
//       header('Cache-Control: must-revalidate');
//       header('Pragma: public');
//       //header('Content-Length: ' . filesize('software-license-policy-and-process-revision-1.pdf'));
//       exit();
//   //}
// }

// Action allows the comment automatic approval to be over-ridden.
function loggedin_approved_comment($approved)
{
	// No need to do the check if the comment is already approved anyway.
	if (is_user_logged_in() || !is_user_logged_in()) {
		// Note: 1/0, not true/false
		$approved = 0;
	}

	return $approved;
}
add_action('pre_comment_approved', 'loggedin_approved_comment');

add_action('wp_ajax_nopriv_p3_comment_approve', 'awp_p3_comment_approve');
add_action('wp_ajax_p3_comment_approve', 'awp_p3_comment_approve');
function awp_p3_comment_approve()
{
	// if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'p3_comment_moderation' ) ) {
	//     exit("Go away!"); //If nonce check fails stop everything
	// }
	$comment_id = $_POST["comment_id"];
	//echo $comment_id;die;
	$success = wp_set_comment_status($comment_id, 'approve');
	$success = update_comment_meta($comment_id, 'p3_comment_status', '');

	if ($success = true) {
		$result['type'] = 'success';
		$result['comment_id'] = $_REQUEST["comment_id"];
	} else {
		$result['type'] = 'error';
		$result['comment_id'] = $_REQUEST["comment_id"];
		echo "Something didn't work";
	}

	if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
		$result = json_encode($result);
		echo $result;
	} else {
		echo "Something didn't work";
		//header("Location: ".$_SERVER["HTTP_REFERER"]);
	}

	die();
}


add_filter('comment_post_redirect', 'redirect_after_comment');
function redirect_after_comment($location)
{
	if (strpos($_SERVER["HTTP_REFERER"], '?') > 0) {
		$string = substr($_SERVER["HTTP_REFERER"], 0, strpos($_SERVER["HTTP_REFERER"], '?'));
		return $string . '?thankyou';
	} else {
		return $_SERVER["HTTP_REFERER"] . '?thankyou';
	}
}


/*Project Add*/
add_action('wp_ajax_nopriv_project_add_newt', 'project_add_new');
add_action('wp_ajax_project_add_new', 'project_add_new');
function project_add_new()
{
	if (empty($_REQUEST['slug']))
		return;

	$slug = $_REQUEST['slug'];

	$post_details = get_post_id_by_name(wp_strip_all_tags($slug), 'post');
	$post_id = $post_details->ID;

	$my_post = array(
		'ID' =>  $post_id,
		'post_status'  => 'private',
	);
	$update_status = wp_update_post($my_post);

	if ($update_status != 0) {
		echo 1;
	} else {
		echo 0;
	}
	die;
}



function display_category_posts($category_id, $curr_post_slug)
{

	$currnet_id = get_the_ID();
	// Get the category object
	$category = get_category($category_id);

	// Get the list of all child categories of the current category
	$child_categories = get_categories(array(
		'child_of' => $category_id,
		'hide_empty' => false,
		'fields' => 'ids',
	));

	// Setup WP_Query to fetch posts only from the current category and not from subcategories
	$args = array(
		'posts_per_page' => -1,
		'category' => $category_id,
		'post_status' => (function_exists('check_admin') && check_admin()) ? array('private', 'publish') : array('publish'),
		'orderby' => 'post_date',
		//'orderby' => 'modified', 
		'order' => 'ASC',
		'category__in' => array($category_id), // Ensure only posts from the current category
		'category__not_in' => $child_categories, // Exclude posts from child categories
		'meta_query' => array(
			'relation' => 'OR',
			array(
				'key' => 'is_archived',
				'compare' => 'NOT EXISTS',
			),
			array(
				'key' => 'is_archived',
				'value' => '1',
				'compare' => '!=',
			),
		),
	);

	$query = new WP_Query($args);

	if ($query->have_posts()):
	echo '<ul>';
	while ($query->have_posts()): $query->the_post();
	$post_slug = get_post_field('post_name', get_post());
	$postID = get_post_field('ID', get_post());
?>
<li class="post">
	<a href="<?php the_permalink(); ?>" class="<?php echo $currnet_id == $postID ? 'active-tab' : ''; ?>"><?php the_title(); ?></a>
</li>
<?php endwhile;
	echo '</ul>';
	endif;

	wp_reset_postdata();
}


function display_categories($parent_id, $curr_post_slug) {

	$p_categories = wp_get_post_categories(get_the_ID());
	// 	echo "<pre>";
	// 	print_r($p_categories);
	// 	echo "</pre>";
	// $p_categories = [];
	$args = array(
		'parent' => $parent_id,
		'hide_empty' => 0,
	);
	$categories = get_categories($args);

	// Get current post category and its ancestors
	$current_category = get_the_category();
	$curr_cat_id = !empty($current_category) ? $current_category[0]->term_id : 0;
	if (!empty($categories)) {
		foreach ($categories as $i => $category) {
			$is_open = in_array($category->term_id, $p_categories);
			$bar_status = $is_open ? 'show' : '';
			$aria_status = $is_open ? 'true' : 'false';
?>
<div class="category-section">
	<button class="sidebar-title-postHeading" type="button" data-bs-toggle="collapse" data-bs-target="#multiCollapseExample<?php echo $category->term_id; ?>" aria-expanded="<?php echo $aria_status; ?>" aria-controls="multiCollapseExample<?php echo $category->term_id; ?>">
		<?php echo $category->name; ?>
		<span class="<?php echo $is_open ? 'sidebar-icon-open' : 'sidebar-icon-close'; ?>">
			<i class="<?php echo $is_open ? 'fa fa-angle-up' : 'fa fa-angle-down'; ?>"></i>
		</span>
	</button>
	<div class="policySectionHeight scrollbar multi-collapse collapse <?php echo $bar_status; ?>" id="multiCollapseExample<?php echo $category->term_id; ?>">
		<div class="sidebar-menu-post">
			<div class="menu-home-page-menu-container">
				<div class="panel-group" id="accordion">
					<div class="panel-default">
						<?php
			// Display posts for the current category
			display_category_posts($category->term_id, $curr_post_slug);

			// Recursively display subcategories
			display_categories($category->term_id, $curr_post_slug);
						?>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
<?php
		}
	}
}

