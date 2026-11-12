<?php
/* Template Name: New Custom Archive Template */
/**
 * The template for displaying archive pages
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package Dosth
 */
get_header();
?>

<?php if(hr_user_can_manage_policies()) { ?>
<div class="row policyHeadingText">
	<div class="col-md-2 navsideBarSec">
		<?php echo get_template_part('template-parts/content', 'sidebar'); ?>
	</div>
	<!-- tab content -->
	<div class="col-md-10">
		<div class="tab-content">
			<div class="tab-pane active text-style" id="">
				<div class="card m-2 bg-sidebar p-0">
					<div class="card-header">
						Archives
					</div>
					<div class="card-body">
						<div class="PolicySecForm m-0">
							<div class="PolicySecForm">
								<div class="row">
									<div class="col-md-6">
										<label for="category" class="formLabel">Category</label>
										<select name="event-dropdown" class="form-control selectControl" onchange='document.location.href=this.options[this.selectedIndex].value;'> 
											<option value=""><?php echo esc_attr(__('Select')); ?></option> 

											<?php 
										  $categories = get_categories(
											  array(
												  'exclude' => 1,
											  )
										  );
										  $option = '';
										  foreach ($categories as $category) {
											  $option .= '<option value="'.get_option('home').'/category/'.$category->slug.'">';
											  $option .= $category->cat_name;
											  $option .= '</option>';
										  }
										  echo $option;
											?>
										</select>
									</div>
									<div class="col-md-6 MarginTopSecMobile">
										<label for="year" class="formLabel">Year</label>
										<select name="year" id="year" class="form-control selectControl" role="menu" onchange="document.location.href=this.options[this.selectedIndex].value;">
											<option value="">Select</option>
											<?php wp_get_archives( array(
												'type' => 'yearly', 
												'format' => 'option', 
												'class' => 'form-control selectControl',
											)); ?>
										</select>
									</div>
								</div>
							</div>
						</div>
						<div class="ReviewBoardSec">
							<div class="ReviewBoradData formarginTop2">
								<?php
								$paged = (get_query_var('paged')) ? absint(get_query_var('paged')) : 1;
								$archive_args = array(
									'post_type' => 'post',
									'page_template'  => 'single-policy.php',
									'post_status' => array('private','publish'),
									'posts_per_page' => 10,
									'paged' => $paged,
									'exclude' => 1,
									'meta_query' => array(
										array(
											'key' => 'is_archived',
											'value' => '1',
											'compare' => '=',
										),
									),
								);
								$archive_posts = new WP_Query($archive_args);

								if ($archive_posts->have_posts()) :
									while ($archive_posts->have_posts()) :
										$archive_posts->the_post();
										$slug = get_post_field('post_name', get_post());
										echo '<div class="formarginTop">
											<span class="ReviewBoardSecHeadingText"><a href="'.get_the_permalink(get_the_ID()).'">'.get_the_title().'</a></span>
											<p class="ReviewBoardSecText1">'.strtoupper(get_the_date(get_option('date_format'),get_the_ID())).'</p>';

										if (current_user_can('policy-admin') || current_user_can('administrator')) {
											echo '<a href="'.site_url().'/admin/archives/?slug='.$slug.'" class="restore-post" data-slug="'.esc_attr($slug).'">Restore</a>';
										}

										echo '</div>';
									endwhile;
									post_pagination($paged, $archive_posts->max_num_pages);
								else :
									echo '<p>No archived policies found.</p>';
								endif;
								wp_reset_postdata();
								?>
							</div>
							<hr>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
<?php }else {?>
<p>You do not have access to this page.</p>
<?php } ?>
<?php get_footer(); ?>