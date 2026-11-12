<article class="search-article">
	<header class="entry-header">
	  <?php if(has_post_thumbnail()):?>

		<img src="<?php the_post_thumbnail_url('blog-small');?>" alt="<?php the_title();?>" class="img-fluid mb-3 img-thumbnail mr-4">

	  <?php endif;?>
	  <h3 class="entry-title">
	    <a href="<?php the_permalink();?>" rel="bookmark"><?php the_title();?></a>
	  </h3>
	  <div class="entry-meta">
	  </div><!-- .entry-meta -->
	</header><!-- .entry-header -->

	<div class="entry-summary">
	  <!--?php/* cyberchimps_featured_image();*/ ?-->
	  <p><?php the_excerpt();?></p>
	</div><!-- .entry-summary -->
</article>


