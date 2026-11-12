<?php
/*
Template Name: Blog Posts Page
*/
?>




<?php get_header();?>

<?php 
	$args = array(
      'posts_per_page'   => -1,
      'offset'           => 0,
      'orderby'          => 'date',
      'order'            => 'ASC',
      'post_type'        => 'policies',

    );
    $wq = new WP_Query($args); // $args are the same as the args for query_posts()
?>

<section class="page-wrap">
<div class="container">
	
			<div id="primary" class="content-area">
					<div id="content" class="site-content" role="main">
					<?php if ($wq->have_posts()) : ?><?php while ($wq->have_posts()) : $wq->the_post(); ?>

					<h1><?php the_title();?></h1>
					<p><?php echo get_the_date('l jS F, Y');?></p>
					<?php the_content();?>

					<?php 
					$fname = get_the_author_meta('first_name');
					$lname = get_the_author_meta('last_name');
					?>

					<p>Posted by <?php echo $fname;?> <?php echo $lname;?></p>


					<?php
					$tags = get_the_tags();
					if($tags):
					foreach($tags as $tag):?>

							<a href="<?php echo get_tag_link($tag->term_id);?>" class="badge badge-success">
								<?php echo $tag->name;?>
							</a>

					<?php endforeach; endif;?>


					<?php 
					$categories = get_the_category();
					foreach($categories as $cat):?>
						
							<a href="<?php echo get_category_link($cat->term_id);?>">
								<?php echo $cat->name;?>
							</a>


					<?php endforeach;?>
							
					<?php // comments_template();?>


				<?php endwhile; else: endif;?>
						
					</div><!-- #content -->
				</div><!-- #primary -->
	

</div>
</section>


<?php get_footer();?>