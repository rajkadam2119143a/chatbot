<?php
/*
Template Name: Admin Template
Template Post Type: post,page
*/
get_header(); ?>

<?php if (hr_user_can_manage_policies()) { ?>
<div class="container-fluid hidescroll">
	<div class="row policyHeadingText">
		<div class="col-md-2 navsideBarSec">
			<div class="bg-sidebar">
				<span class="sidebar-title-postHeading">Admin Panel</span>
				<div class="BorderBottom"></div>
				<div class="policySectionHeight scrollbar">
					<div class="sidebar-menu-post2">
						<div class="menu-home-page-menu-container">
							<div class="panel-group" id="accordion">
								<div class="panel-default">
									<ul>
										<li>
											<a href="<?php echo get_permalink(7); ?>" class="<?php if (strpos($_SERVER['REQUEST_URI'], '/add') !== false) echo 'ActivepolicyHeadingNav'; ?>">Add New Policy</a>
										</li>
										<li>
											<a href="<?php echo get_permalink(13); ?>" class="<?php if (strpos($_SERVER['REQUEST_URI'], '/search') !== false || strpos($_SERVER['REQUEST_URI'], '/edit') !== false) echo 'ActivepolicyHeadingNav'; ?>">Edit/Search Policy</a>
										</li>
										<li>
											<a href="<?php echo get_permalink(9); ?>" class="<?php if (strpos($_SERVER['REQUEST_URI'], '/archives') !== false) echo 'ActivepolicyHeadingNav'; ?>">Archives</a>
										</li>
									</ul>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<?php 
										   if (isset($_GET['slug'])) {
											   $slug = sanitize_title(wp_strip_all_tags($_GET['slug']));

											   // Fallback if get_post_id_by_name is undefined
											   if (function_exists('get_post_id_by_name')) {
												   $post_details = get_post_id_by_name($slug, 'post');
											   } else {
												   $post_details = get_page_by_path($slug, OBJECT, 'post');
											   }

											   if ($post_details) {
												   $post_id = $post_details->ID;
												   $post_data = get_post_meta($post_id, 'document_revisions_details');

												   if (!empty($post_data[0])) {
													   $doc_id = $post_data[0];
													   $attachments = get_document_revisions($doc_id);

													   if (!empty($attachments)) {
														   echo '<div class="bg-sidebar archives-sidebar">';
														   echo '<span class="sidebar-title-postHeading">Related Files</span><div class="BorderBottom"></div><div class="policySectionHeight scrollbar related-files"><div class="sidebar-menu-post1"><div class="menu-home-page-menu-container"><div class="panel-group" id="accordion"><div class="panel-default"><ul class="">';

														   foreach ($attachments as $value) {
															   if ($value->post_status !== 'publish' && $value->post_excerpt != '') {
																   $file_url = ('attachment' === get_post_type($value->ID)) ? wp_get_attachment_url($value->ID) : get_permalink($value->ID);
																   echo "<li><a target='_blank' href='" . esc_url($file_url) . "' download>" . esc_html($value->post_title) . "</a>";
																   echo '<p class="ReviewBoardSecsubtext">' . strtoupper(get_the_date(get_option('date_format'), $value->ID)) . '</p>';
																   echo "</li>";
															   }
														   }

														   echo '</ul></div></div></div></div></div></div>';
													   }
												   }
											   }
										   }

										   echo '<div class="archives"></div>';
			?>
		</div>

		<div class="col-md-10">
			<div class="tab-content">
				<div class="tab-pane active text-style">

					<?php
										   $uri_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
										   $uri_segments = explode('/', trim($uri_path, '/'));
										   $last_segment_val = $uri_segments[array_key_last($uri_segments)];

										   if ($last_segment_val == 'add') { ?>
					<span class="AdminpolicyHeadingText">Add Policy</span>
					<div class="BorderBottom2"></div>
					<?php } elseif ($last_segment_val == 'edit') { ?>
					<span class="AdminpolicyHeadingText">Edit Policy</span>
					<div class="BorderBottom2"></div>
					<?php } elseif ($last_segment_val == 'search') { ?>
					<span class="AdminpolicyHeadingText">Search Policy</span>
					<div class="BorderBottom2"></div>
					<?php } ?>

					<div class="PolicySecForm">
						<?php get_template_part('template-parts/content', 'admin'); ?>
					</div>

				</div>
			</div>
		</div>
	</div>
</div>
<?php } else { ?>
<p>You do not have access to this page.</p>
<?php } ?>

<?php get_footer(); ?>
