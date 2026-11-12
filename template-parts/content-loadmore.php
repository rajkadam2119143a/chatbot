<div class="policyBox paddingDiv">
    <div class="bg-sidebar1">
        <?php 
        $category = get_the_category();

        if (function_exists('get_term_image_url')) 
        $icon_url = get_cat_icon($category[0]->term_id,'pix_term_icon');
        ?>
        <div class="policyImg-box">
          <img src="<?php echo $icon_url; ?>">
        </div>
        <p class="sidebar-title-post"><?php the_title(); ?></p>
        <p class="description"><?php the_excerpt_rss(); ?>
          <span id="dots">...</span>
        </p>
        <div class="read-more-box"><a class="except-more" href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>">Read More</a></div>
    </div>
</div>