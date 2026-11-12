<?php
/**
 * The template for displaying Tag pages
 *
 * Used to display archive-type pages for posts in a tag.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package WordPress
 * @subpackage Twenty_Twelve
 * @since Twenty Twelve 1.0
 */
?>

<?php get_header();?>

<div class="PoliciesSecMainDiv container-fluid">
  <?php dynamic_sidebar('admin-sidebar'); ?>
  <div class="col-md-9">

      <div class="row search-box">
        <div class="col-md-8">
            <?php get_search_form();?>
         </div>
      </div>

      <!--Acceptable_Use_Policy heading-->
      <div class="row Acceptable_Use_Policy_HeadingDiv">
         <div class="policyImg-boxDiv">
            <div class="policyImg-box1">
               <img src="<?php echo get_stylesheet_directory_uri(); ?>/images/Acceptable_Use_Policy.png">
            </div>
         </div>
         <div class="policyTextDiv">
            <p class="title-post"><?php
                /* translators: %s: Tag title. */
                printf( __( 'Tag Archives: %s', 'policies' ), '<span>' . single_tag_title( '', false ) . '</span>' );
                ?></p>
            <?php if ( tag_description() ) : // Show an optional tag description. ?>
                <div class="archive-meta"><?php echo tag_description(); ?></div>
            <?php endif; ?>
         </div>
      </div>

      <div class="row col-md-12">
        <?php
            $tag = get_queried_object();
            $original_query = $wp_query;
            $wp_query = null;
            $args = array('posts_per_page' => 5, 'tag' => $tag->slug);
            $wp_query = new WP_Query($args);

            if (have_posts()) :
                while (have_posts()) : the_post();
                    get_template_part( 'template-parts/content', 'tagarticles' );
                endwhile;
            endif;

            $wp_query = null;
            $wp_query = $original_query;
            wp_reset_postdata();
        ?>
      </div>

   </div>

</div>

<?php get_footer();?>