<?php
/*
Template Name: Add Policy
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
					<span class="AdminpolicyHeadingText">Add Policy</span><div class="BorderBottom2"></div>
					<div class="PolicySecForm">
						<?php
						if (!session_id()) session_start();
						wpshout_save_post_if_submitted();

						$token = md5(uniqid(mt_rand(), true));
						$_SESSION['hidden_admin'] = $token;

						?>

						<form id="new_post" name="new_post" method="post" enctype="multipart/form-data">
							<input type="hidden" name="hidden-1" value="<?php echo esc_attr($token); ?>">

							<!-- Name of Policy -->
							<div class="row formarginTop">
								<div class="col-md-6">
									<div class="form-group">
										<label for="title" class="formLabel">Name of Policy<span class="mandatorySymbol"> *</span></label>
										<input type="text" class="form-control" id="title" name="title" required />
									</div>
								</div>

								<!-- Category -->
								<div class="col-md-6 MarginTopSecMobile">
									<label for="cat_search" class="formLabel">Category<span class="mandatorySymbol"> *</span></label>
									<?php
									wp_dropdown_categories(array(
										'taxonomy' => 'category',
										'hide_empty' => false,
										'hierarchical' => true,
										'show_option_none' => "Select",
										'option_none_value' => "",
										'name' => 'cat',
										'id' => 'cat_search',
										'class' => 'form-control selectControl',
										'value_field' => 'term_id',
										'exclude' => 1,
										'required' => true
									));
									?>
								</div>
							</div>

							<!-- Keywords and Purpose -->
							<div class="row formarginTop">
								<div class="col-md-6">
									<label for="post_tags" class="formLabel">Keywords</label>
									<textarea class="form-control textareClass" id="post_tags" name="post_tags" rows="4"></textarea>
									<p><i>Comma separated values only.</i></p>
								</div>
								<div class="col-md-6 MarginTopSecMobile">
									<label for="post_excerpt" class="formLabel">Purpose/Scope</label>
									<textarea class="form-control textareClass" id="post_excerpt" name="post_excerpt" rows="4"></textarea>
									<div class="word-counter"><i>Word Count - 0/60</i></div>
								</div>
							</div>

							<!-- File Upload -->
<div class="row formarginTop">

    <div class="col-md-6 MarginTopSec">

        <!-- Main Upload -->
        <div class="policy-file">

            <label class="formLabel1" for="post_file">

                <img 
                    id="imgFileUpload" 
                    class="uploadimg" 
                    src="<?php echo esc_url(get_stylesheet_directory_uri() . '/images/file_upload.png'); ?>" 
                />

                <span>
                    Upload Policy
                    <span class="mandatorySymbol"> *</span>
                </span>

            </label>

            <span id="spnFilePath" class="SeletcedFileName"></span>

            <p class="uploadInvoicetext">
                <i>
                    (Allowed: PDF, DOC, DOCX, XLS, XLSX, CSV | Max 4 MB)
                </i>
            </p>

            <input
                type="file"
                id="post_file"
                name="post_file"
                accept="
                    .pdf,
                    .doc,
                    .docx,
                    .xls,
                    .xlsx,
                    .csv,
                    application/pdf,
                    application/msword,
                    application/vnd.ms-excel,
                    application/vnd.openxmlformats-officedocument.wordprocessingml.document,
                    application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,
                    text/csv
                "
                style="display: none;"
                required
            />

        </div>

        <!-- Optional PDF Upload -->
        <div class="mappwithdocpdf" style="display: none;">

            <div class="policy-file">

                <label class="formLabel1" for="post_file1">

                    <img 
                        id="imgFileUpload1" 
                        class="uploadimg" 
                        src="<?php echo esc_url(get_stylesheet_directory_uri() . '/images/file_upload.png'); ?>" 
                    />

                    <span>
                        Upload Policy (PDF)
                        <span class="mandatorySymbol"> *</span>
                    </span>

                </label>

                <span id="spnFilePath1" class="SeletcedFileName"></span>

                <p class="uploadInvoicetext">
                    <i>(Upload PDF below 4 MB only)</i>
                </p>

                <input
                    type="file"
                    id="post_file1"
                    name="post_file1"
                    accept=".pdf,application/pdf"
                    style="display: none;"
                />

            </div>

        </div>

    </div>

    <!-- Access -->
    <div class="col-md-6">

        <label for="post_status" class="formLabel">
            Access
        </label>

        <select 
            name="post_status" 
            id="post_status" 
            class="form-control selectControl" 
            required
        >
            <option value="public">Public</option>
            <option value="private">Private</option>
        </select>

    </div>

</div>
							<!-- Policy Date -->
							<div class="row formarginTop">
								<div class="col-md-6">
									<label for="post_policy_date" class="formLabel1">Policy Date <span class="uploadInvoicetext"><i>(Add last updated date of policy)</i></span></label>
									<div class="form-group">
										<div class="input-group date" data-date-format="dd/mm/yyyy">
											<input type="text" class="form-control" id="post_policy_date" name="post_policy_date" placeholder="dd/mm/yyyy" />
											<div class="input-group-addon">
												<span class="input-group-text">
													<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-calendar" viewBox="0 0 16 16">
														<path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z" />
													</svg>
												</span>
											</div>
										</div>
									</div>
								</div>
							</div>

							<?php wp_nonce_field('wps-frontend-post'); ?>

							<div class="read-more-box formarginTop marginBottomDiv">
								<button type="submit" class="btnCss" id="submit" name="submit">
									<span class="except-more">
										Add Policy <img src="<?php echo esc_url(get_stylesheet_directory_uri() . '/images/redArrow.png'); ?>" class="Redarraowimg" alt="Add">
									</span>
								</button>
							</div>
						</form>
					</div>
				</div> <!-- .tab-pane -->
			</div> <!-- .tab-content -->
		</div> <!-- .col-md-9 -->
	</div> <!-- .row -->
</div>
<?php else : ?>
<p>You do not have access to this page.</p>
<?php endif; ?>

<?php get_footer(); ?>
