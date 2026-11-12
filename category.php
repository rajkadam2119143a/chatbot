<?php
get_header();

$uri_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri_segments = explode('/', $uri_path);
//print_r($uri_segments);
$catSlug = $uri_segments[4];
$catObj = get_category_by_slug($catSlug); 
$catName = $catObj->name;
wp_reset_query();
?>

<?php if(hr_user_can_manage_policies()) { ?>
<div class="row policyHeadingText">
	<div class="col-md-2 navsideBarSec">
		<?php get_template_part('template-parts/content', 'sidebar'); ?>

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

							<?php if ( have_posts() ) : ?>
							<?php
										  $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;

										  $args = array(
											  'paged' => $paged,
											  'post_type' => 'post',
											  'posts_per_page' => 2,
											  'category_name' => $catSlug,
											  'post_status' => array('private','publish'),
											  'meta_query' => array(
											  	array(
											  		'key' => 'is_archived',
											  		'value' => '1',
											  		'compare' => '=',
											  	),
											  ),
										  );
										  //print_r($args);
										  $postslist = new WP_Query( $args );

										  $i = 0;            
										  while ( $postslist->have_posts() ) : $postslist->the_post();                
							?> 

							<div class="mb-2">
								<span class="ReviewBoardSecHeadingText"><a href="<?php echo get_the_permalink(get_the_ID()); ?>"><?php the_title(); ?></a></span>
								<p class="ReviewBoardSecText1"><?php echo strtoupper(get_the_date( get_option('date_format'),get_the_ID())); ?></p>
								<?php
								if (current_user_can('policy-admin') || current_user_can('administrator')) {
									$slug = get_post_field('post_name', get_post());
									echo '<a href="'.site_url().'/admin/archives/?slug='.$slug.'" class="restore-post" data-slug="'.esc_attr($slug).'">Restore</a>';
								}
								?>
							</div>

							<?php endwhile; ?>
							<?php post_pagination($paged,$postslist->max_num_pages); ?>
							<?php else : ?>

							<article id="post-0" class="post no-results not-found">
								<header class="entry-header">
									<h1 class="entry-title"><?php _e( 'Nothing Found'); ?></h1>
								</header><!-- /entry-header -->

								<div class="entry-content">
									<p><?php _e( 'Apologies, but no results were found for the requested archive. Perhaps searching will help find a related post.', 'fairpixels' ); ?></p>
									<?php get_search_form(); ?>
								</div><!-- /entry-content -->
							</article><!-- /post-0 -->

							<?php endif; ?>
							<?php wp_reset_postdata(); ?>
						</div>
					</div>
				</div>
			</div>
		</div>
<!-- 		<div class="ReviewBoardSec">
			<div class="ReviewBoradData formarginTop2" ></div>       
			<hr>
		</div> -->

	</div>
</div>
<?php }else {?>
<p>You do not have access to this page.</p>
<?php } ?>

<?php get_footer(); ?>