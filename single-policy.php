<?php
/*
Template Name: Policy Template
Template Post Type: post
*/

get_header();
?>

<div class="PoliciesSecMainDiv container-fluid">
	<div class="row">
	<?php
	if (function_exists('check_admin') && check_admin()) {
		get_sidebar('admin');
	} else {
		get_sidebar();
	}

	$category = get_the_category();
	$cat_slug = ! empty($category) ? $category[0]->slug : '';

	$icon_url = '';
	if (function_exists('get_term_image_url') && !empty($category) && isset($category[0])) {
		$icon_url = get_cat_icon($category[0]->term_id, 'pix_term_icon');
		if (empty($icon_url) && !empty($category[0]->category_parent)) {
			$icon_url = get_cat_icon($category[0]->category_parent, 'pix_term_icon');
		}
	}
	if (empty($icon_url)) {
		$icon_url = get_the_permalink(159);
	}

	$post_id = get_the_ID();
	$post_policy_date = get_post_meta($post_id, 'post_policy_date', true);
	$is_archived = get_post_meta($post_id, 'is_archived', true);
	$can_view_archived = function_exists('check_admin') && check_admin();

	if (!empty($is_archived) && !$can_view_archived) {
		echo '<div class="col-md-9"><p>This policy has been archived.</p></div></div></div>';
		get_footer();
		return;
	}

	if (!empty($post_policy_date)) {
		$date_parts = explode('/', $post_policy_date);
		if (count($date_parts) === 3) {
			$converted_date = $date_parts[2] . '-' . $date_parts[1] . '-' . $date_parts[0];
			$timestamp = strtotime($converted_date);
			$updated_date = $timestamp ? date('d M Y', $timestamp) : 'Invalid converted date';
		} else {
			$updated_date = 'Invalid format';
		}
	} else {
		$updated_date = get_the_modified_date('d M Y', $post_id);
	}
	?>

	<div class="col-md-9">

		<!-- Policy Heading -->
		<div class="row Acceptable_Use_Policy_HeadingDiv">
			<div class="policyImg-boxDiv">
				<div class="policyImg-box1">
					<?php if ($icon_url): ?>
					<img src="<?php echo esc_url($icon_url); ?>" alt="Policy Icon">
					<?php endif; ?>
				</div>
			</div>
			<div class="policyTextDiv">
				<p class="title-post"><?php the_title(); ?></p>
				<span class="ReviewBoardSecText1"><?php echo strtoupper($updated_date); ?></span>
			</div>
		</div>

		<!-- Policy Purpose -->
		<div>
			<p class="Subtitle-post"><?php echo esc_html(get_the_excerpt()); ?></p>
		</div>

		<?php
		$post_id = get_the_ID();
		$post_policy_date = get_post_meta($post_id, 'post_policy_date', true);
		$doc_id = get_post_meta($post_id, 'document_revisions_details', true);

		if (empty($doc_id)) {
			$doc_id = get_post_meta($post_id, 'document_id', true);
		}
		$doc_to_pdf = get_post_meta($post_id, 'doc_to_pdf', true);

		$pdf_url = '';
		$other_url = '';
		if (!empty($doc_to_pdf)) {
			$pdf_url = site_url() . $doc_to_pdf;
			$other_url = !empty($doc_id) ? get_permalink($doc_id) : '';
		} else {
			// Fallback: try to get from document post if needed - matches IT policies
			$file_path = '';
			if (!empty($doc_id)) {
				$file_path = get_post_meta($doc_id, '_wp_attached_file', true);
				if (empty($file_path)) {
					$child_posts = get_posts([
						'post_parent' => $doc_id,
						'post_type'   => 'attachment',
						'numberposts' => -1,
						'post_status' => 'inherit',
					]);
					$end_file = reset($child_posts);
					if ($end_file) {
						$file_path = get_post_meta($end_file->ID, '_wp_attached_file', true);
					}
				}
				if (!empty($file_path)) {
					$upload_dir = wp_upload_dir();
					$possible_url = $upload_dir['baseurl'] . '/' . $file_path;
					$file_ext = strtolower(pathinfo(parse_url($possible_url, PHP_URL_PATH), PATHINFO_EXTENSION));
					if ($file_ext === 'pdf') {
						$pdf_url = $possible_url;
					} else {
						$other_url = $possible_url;
					}
				}
			}
		}
		?>
		<?php
		// Multisite: use parent path (bccl) for shared assets, not site-specific path (bccl/F91XZPLK8830Q)
		$upload_base = ( is_multisite() ) ? preg_replace( '#/[^/]+/?$#', '', trailingslashit( site_url() ) ) : site_url();
		$download_icon_url = trailingslashit( $upload_base ) . 'wp-content/uploads/sites/3/2024/04/download.png'; 
		?>
		<div class="pdf-viewer">
			<?php

$download_url = '';

// 1) File recorded at upload time (latest primary upload) - always current.
$latest_download = get_post_meta($post_id, 'policy_download_url', true);
if (!empty($latest_download)) {
    $download_url = site_url() . $latest_download;
}

// 2) Policies saved before that meta existed: newest attachment of the document.
//    (Do not use get_permalink($doc_id) - it resolves through the WPDR revision chain and serves old files.)
if (empty($download_url) && !empty($doc_id)) {
    $latest_files = get_posts([
        'post_parent' => $doc_id,
        'post_type'   => 'attachment',
        'numberposts' => 1,
        'post_status' => 'inherit',
        'orderby'     => 'ID',
        'order'       => 'DESC',
    ]);
    if (!empty($latest_files)) {
        $download_url = wp_get_attachment_url($latest_files[0]->ID);
    }
}

// 3) Last resort: the file shown in the viewer.
if (empty($download_url) && !empty($pdf_url)) {
    $download_url = $pdf_url;
}

if (!empty($download_url)) :

    // GET EXTENSION
    $extension = pathinfo(
        parse_url($download_url, PHP_URL_PATH),
        PATHINFO_EXTENSION
    );

    // EXACT POLICY TITLE
    $download_filename =
        get_the_title() . '.' . $extension;
?>

<a
    href="<?php echo esc_url($download_url); ?>"
    download="<?php echo esc_attr($download_filename); ?>"
    style="font-size:14px;padding:10px;display:inline-block;float:right;color:#000;"
>
    Download
    <img
        src="<?php echo esc_url($download_icon_url); ?>"
        width="20px"
        alt="Download Icon"
    />
</a>

<?php endif; ?>			<?php if (!empty($pdf_url)) : ?>
			<?php echo do_shortcode('[wonderplugin_pdf src="' . esc_url($pdf_url) . '" width="100%" height="600px" style="border:0;"]'); ?>
			<?php endif; ?>
		</div>

		<?php if (!empty($post_policy_date)) : ?>
		<p class="pull-right">Policy Date: <?php echo esc_html($post_policy_date); ?></p>
		<?php endif; ?>

		<?php
		//if (!post_password_required()) {
		//	comments_template('/comment-template.php', true);
		//}
		?>
	</div>
	</div>
</div>

<?php get_footer(); ?>
