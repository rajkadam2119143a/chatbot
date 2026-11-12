<?php
/*
Template Name: Edit Policy
Template Post Type: post, page
*/
get_header();
?>

<?php if (hr_user_can_manage_policies()) : ?>
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
										<li><a href="<?php echo get_permalink(7); ?>" class="<?php if (is_page('add')) echo 'ActivepolicyHeadingNav'; ?>">Add New Policy</a></li>
										<li><a href="<?php echo get_permalink(13); ?>" class="<?php if (is_page('search') || is_page('edit')) echo 'ActivepolicyHeadingNav'; ?>">Edit/Search Policy</a></li>
										<li><a href="<?php echo get_permalink(9); ?>" class="<?php if (is_page('archives')) echo 'ActivepolicyHeadingNav'; ?>">Archives</a></li>
									</ul>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<div class="col-md-10">
			<div class="tab-content">
				<div class="tab-pane active text-style">			
					<span class="AdminpolicyHeadingText">Edit Policy</span><div class="BorderBottom2"></div>
					<div class="PolicySecForm">
						<?php
						wpshout_edit_save_post_if_submitted();

						// FIX 4: Initialise all variables with safe defaults so the form never
						// references undefined variables (causes PHP notices + broken hidden field).
						$post_id          = 0;
						$post_title       = '';
						$post_tags        = [];
						$post_status      = 'publish';
						$cat              = '';
						$post_excerpt_raw = '';
						$post_policy_date = '';
						$slug             = '';

						if (isset($_GET['slug'])) {
							$slug         = sanitize_text_field($_GET['slug']);
							$post_details = get_post_id_by_name($slug, 'post');

							if ($post_details) {
								$post_id    = $post_details->ID;
								$post_obj   = get_post($post_id);

								$post_title  = get_the_title($post_id);
								$post_tags   = get_the_tags($post_id);
								$post_status = get_post_status($post_id);
								$categories  = get_the_category($post_id);
								$cat         = !empty($categories) ? $categories[0]->term_id : '';

								// FIX 5: Load raw post_excerpt from DB — NOT the truncated display version
								// from wpse69204_excerpt(). The truncated version was causing the excerpt
								// to be permanently shortened on every save.
								$post_excerpt_raw = $post_obj ? $post_obj->post_excerpt : '';

								$post_policy_date = get_post_meta($post_id, 'post_policy_date', true);
							}
						}
						?>

						<form id="edit_post" name="edit_post" method="post" enctype="multipart/form-data">
							<?php
							if (!session_id()) session_start();
							$nonce_token = md5(uniqid(mt_rand(), true));
							$_SESSION['hidden_admin'] = $nonce_token;
							?>
							<input type="hidden" name="hidden-1" value="<?php echo esc_attr($nonce_token); ?>">
							<input type="hidden" name="post_id"  value="<?php echo esc_attr($post_id); ?>">

							<div class="row formarginTop">
								<div class="col-md-6">
									<label for="title" class="formLabel">Name of Policy<span class="mandatorySymbol"> *</span></label>
									<input type="text" class="form-control search-field" id="title" name="title"
										value="<?php echo esc_attr($post_title); ?>" required />
								</div>
								<div class="col-md-6 MarginTopSecMobile">
									<label for="cat_search" class="formLabel">Category<span class="mandatorySymbol"> *</span></label>
									<?php
									wp_dropdown_categories([
										'taxonomy'          => 'category',
										'hide_empty'        => 0,
										'hierarchical'      => 1,
										'show_option_none'  => 'Select',
										'option_none_value' => '',
										'name'              => 'cat',
										'id'                => 'cat_search',
										'class'             => 'form-control selectControl',
										'value_field'       => 'term_id',
										'exclude'           => 1,
										'required'          => true,
										'selected'          => $cat,
									]);
									?>
								</div>
							</div>

							<div class="row formarginTop">
								<div class="col-md-6">
									<label for="post_tags" class="formLabel">Keywords</label>
									<textarea class="form-control textareClass" id="post_tags" name="post_tags" rows="4"><?php
										if (!empty($post_tags)) {
											echo esc_textarea(implode(',', wp_list_pluck($post_tags, 'name')));
										}
									?></textarea>
									<p><i>Comma separated values only.</i></p>
								</div>
								<div class="col-md-6 MarginTopSecMobile">
									<label for="post_excerpt" class="formLabel">Purpose/Scope</label>
									<textarea class="form-control textareClass" id="post_excerpt" name="post_excerpt" rows="4"><?php echo esc_textarea($post_excerpt_raw); ?></textarea>
									<div class="word-counter"><i>Word Count - <span id="excerptWordCount">0</span>/60</i></div>
								</div>
							</div>

							<div class="row formarginTop">
								<div class="col-md-6 MarginTopSec">
									<!-- Primary upload: PDF / DOC / XLS -->
									<div class="policy-file">
										<label class="formLabel1" for="post_file">
											<img id="imgFileUpload" class="uploadimg"
												src="<?php echo esc_url(get_stylesheet_directory_uri() . '/images/file_upload.png'); ?>"
												style="cursor:pointer" />
											<span style="cursor:pointer">Upload Policy</span>
										</label>
										<!-- FIX 6: span IDs now match JS below and mirror Add Policy exactly -->
										<span id="spnFilePath" class="SeletcedFileName"></span>
										<?php
$current_pdf = get_post_meta($post_id, 'doc_to_pdf', true);

if (!empty($current_pdf)) :
?>
    <div style="margin-top:10px;">
        Current File:
        <a href="<?php echo esc_url(site_url($current_pdf)); ?>" target="_blank">
            <?php echo basename($current_pdf); ?>
        </a>
    </div>
<?php endif; ?>
										<p class="uploadInvoicetext"><i>(Upload files below 4 MB only)</i></p>
										<input type="file" id="post_file" name="post_file" style="display:none"
											accept=".pdf,application/pdf,
													.doc,.docx,application/msword,
													application/vnd.openxmlformats-officedocument.wordprocessingml.document,
													.xls,.xlsx,application/vnd.ms-excel,
													application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" />
									</div>

									<!--
										FIX 7: Secondary PDF uploader.
										Removed static "hide" class — visibility is now JS-controlled via
										style="display:none" just like Add Policy. JS below shows this block
										only when a non-PDF (DOC/DOCX/XLS/XLSX) is selected as primary file.
									-->
									<div class="mappwithdocpdf" style="display:none;">
										<div class="policy-file">
											<label class="formLabel1" for="post_file1">
												<img id="imgFileUpload1" class="uploadimg"
													src="<?php echo esc_url(get_stylesheet_directory_uri() . '/images/file_upload.png'); ?>"
													style="cursor:pointer" />
												<span style="cursor:pointer">Upload Policy (PDF)<span class="mandatorySymbol"> *</span></span>
											</label>
											<span id="spnFilePath1" class="SeletcedFileName"></span>
											<p class="uploadInvoicetext"><i>(Upload files below 4 MB only)</i></p>
											<input type="file" id="post_file1" name="post_file1"
												style="display:none" accept="application/pdf,.pdf" />
										</div>
									</div>
								</div>

								<div class="col-md-6 MarginTopSecMobile">
									<label for="post_status" class="formLabel">Access</label>
									<select name="post_status" id="post_status" class="form-control selectControl" required>
										<option value="public"  <?php selected($post_status, 'publish'); ?>>Public</option>
										<option value="private" <?php selected($post_status, 'private'); ?>>Private</option>
									</select>
								</div>
							</div>

							<div class="row formarginTop">
								<div class="col-md-6">
									<label for="post_policy_date" class="formLabel1">Policy Date
										<span class="uploadInvoicetext"><i>(Add last updated date of policy)</i></span>
									</label>
									<div class="form-group">
										<div id="filterDate2">
											<div class="input-group date" data-date-format="dd/mm/yyyy">
												<input type="text" class="form-control" placeholder="dd/mm/yyyy"
													id="post_policy_date" name="post_policy_date"
													value="<?php echo esc_attr($post_policy_date); ?>" />
												<div class="input-group-addon">
													<span class="input-group-text">
														<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-calendar" viewBox="0 0 16 16">
															<path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z"/>
														</svg>
													</span>
												</div>
											</div>
										</div>
									</div>
								</div>
							</div>

							<?php wp_nonce_field('wps-frontend-post-edit'); ?>
							<input type="hidden" name="add_data" id="add_data" value="<?php echo esc_attr($slug); ?>">

							<div class="read-more-box formarginTop marginBottomSec">
								<button type="submit" class="btnCss" id="submit" name="submit">
									<span class="except-more">
										Update Policy <img src="<?php echo esc_url(get_stylesheet_directory_uri() . '/images/redArrow.png'); ?>" class="Redarraowimg" alt="Update">
									</span>
								</button>
							</div>
						</form>

					</div>
				</div><!-- /.tab-pane -->
			</div><!-- /.tab-content -->
		</div><!-- /.col-md-10 -->
	</div><!-- /.row -->
</div>


<?php else : ?>
<p>You do not have access to this page.</p>
<?php endif; ?>

<?php get_footer(); ?>