<?php
/*
Template Name: Admin Template
*/
?>

<?php get_header(); ?>

<div class="PoliciesSecMainDiv container-fluid">
  <div class="row">


    <?php get_sidebar(); ?>

    <div class="col-md-9">

      <div class="row search-box mobileview">
        <div class="col-md-8">
          <?php get_search_form(); ?>
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
                                /* translators: %s: search query. */
                                printf(esc_html__('Search Results for: %s', 'policies'), '<span>' . get_search_query() . '</span>');
                                ?></p>
        </div>
      </div>

      <div class="row col-md-12">
        <?php if (have_posts()) : ?>
          <?php
          while (have_posts()) : the_post();
          ?>
            <?php
            /**
             * Run the loop for the search to output the results.
             * If you want to overload this in a child theme then include a file
             * called content-search.php and that will be used instead.
             */
            get_template_part('template-parts/content', 'search');
            ?>
          <?php endwhile; ?>

          <?php
          // Previous/next page navigation.
          the_posts_pagination(
            array(
              'prev_text'          => __('Prev', 'policies'),
              'next_text'          => __('Next', 'policies'),
              'before_page_number' => '<span class="meta-nav screen-reader-text">' . __('Page', 'policies') . ' </span>',
            )
          );
          ?>

        <?php else : ?>

          <?php echo "No posts found."; ?>
        <?php endif; ?>
      </div>

    </div>
  </div>
</div>

<?php get_footer(); ?>