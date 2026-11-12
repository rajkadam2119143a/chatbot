<?php
get_header();

$uri_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri_segments = explode('/', $uri_path);
$last_segment_year = $uri_segments[3];
$last_segment_month = $uri_segments[4];
wp_reset_query();
?>

<?php if(hr_user_can_manage_policies()) { ?>
<div class="row policyHeadingText">
   <div class="col-md-3 navsideBarSec">
      <div class="bg-sidebar">
         <span class="sidebar-title-postHeading">Admin Panel</span>
         <div class="BorderBottom"> </div>
         <div class="policySectionHeight scrollbar">
            <div class="sidebar-menu-post2">
               <div class="menu-home-page-menu-container">
                  <div class="panel-group" id="accordion">
                     <div class="panel-default">
                        <ul class="">
                           <li><a href="<?php site_url(); ?>/bccl/tools_templates/admin/add" class="<?php if (is_page('add')) echo 'ActivepolicyHeadingNav'; ?>">Add New Policy</a></li>
                            <li><a href="<?php site_url(); ?>/bccl/tools_templates/admin/search" class="<?php if (is_page('search') || is_page('edit')) echo 'ActivepolicyHeadingNav'; ?>">Edit/Search Policy</a></li>
                            <li><a href="<?php site_url(); ?>/bccl/tools_templates/admin/archives" class="ActivepolicyHeadingNav">Archives</a></li>
                        </ul>
                     </div>
                  </div>
               </div>
            </div>
         </div>
      </div>
   </div>
   <!-- tab content -->
   <div class="col-md-9">
       <div class="tab-content">
         <div class="tab-pane active text-style" id="">
            <span class="AdminpolicyHeadingText">Archives</span>
            <div class="BorderBottom2"></div>
            <div class="PolicySecForm">

            <?php if ( have_posts() ) : ?>
            <?php
              $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
              //echo get_query_var('paged');
              $args = array(
             'paged' => $paged,
             'post_type' => 'post',
             'posts_per_page' => 2,
             'date_query' => array(
                    array(
                        'year' => $last_segment_year,
                        //'month' => $last_segment_month
                    )
                ),
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
                
                <div class="formarginTop">
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
              <?php $GLOBALS['wp_query'] = $postslist; ?>
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
            </div>
         </div>
        </div>
        <div class="ReviewBoardSec">
            <div class="ReviewBoradData formarginTop2" ></div>       
            <hr>
        </div>

    </div>
</div>
<?php }else {?>
  <p>You do not have access to this page.</p>
<?php } ?>

<?php get_footer(); ?>