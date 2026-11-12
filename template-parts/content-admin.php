<div class="ReviewBoardSec">
	<div class="ReviewBoradData">
		<?php
		$uri_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
		$uri_segments = explode('/', $uri_path);
		//print_r($uri_segments);
		$last_segment_val = $uri_segments[array_key_last($uri_segments) - 1];


		if($last_segment_val == 'add') {
			echo do_shortcode('[wpshout_frontend_post]');
		}else if($last_segment_val == 'edit'){
			echo do_shortcode('[wpshout_frontend_post_edit]'); 
		}else if($last_segment_val == 'search') {
			echo do_shortcode('[wpshout_frontend_post_search]');
		}

		if($uri_segments[4] != 'edit') {
			if(get_query_var( 'paged' ) || isset($_POST['title']) || isset($_POST['post_tags']) || isset($_POST['cat']) && isset($_POST['hidden-1'])) {

				//echo do_shortcode('[wpshout_frontend_post_search]');

				$paged = ( get_query_var( 'paged' ) ) ? absint( get_query_var( 'paged' ) ) : 1;

				// Add the content of the form to $post as an array
				$args = array(
					'post_type'   => 'post',
					'page_template'  => 'single-policy.php',
					'post_status' => array('private','publish'),
					'posts_per_page' => 10,
					'paged' => $paged,
					'exclude' => 1,
					'order'=>'DESC',
					'meta_query' => array(
						'relation' => 'OR',
						array(
							'key' => 'is_archived',
							'compare' => 'NOT EXISTS',
						),
						array(
							'key' => 'is_archived',
							'value' => '1',
							'compare' => '!=',
						),
					),
				);


				if(isset($_POST['title']) && $_POST['title'] != '') {
					$args['s'] = $_POST['title'];
				}

				if(isset($_POST['post_excerpt']) && $_POST['post_excerpt'] != '') {
					$args['s'] = $_POST['post_excerpt'];
				}

				if(isset($_POST['cat']) && $_POST['cat'] != '') {
					$args['category__in'] = array($_POST['cat']);
				}

				if(isset($_POST['post_tags']) && $_POST['post_tags'] != '') {
					$args['tag'] = $_POST['post_tags'];
				}

				//echo "<pre>";
				//echo "Title---".$args['s'];
				$latest_posts = new WP_Query($args);
				//print_r($latest_posts);

				if($latest_posts->have_posts()):
				while ($latest_posts->have_posts()) :
				$latest_posts->the_post();
				$slug = get_post_field( 'post_name', get_post() );
				$category = get_the_category();
				$cat_id = $category[0]->term_id;
				$post_status = get_post_status(get_the_ID());

				echo '<div class="formarginTop1">
	        <span class="ReviewBoardSecHeadingText"><a href="'.get_the_permalink().'">'.get_the_title().'</a></span>';

				if(current_user_can('policy-admin') || current_user_can('administrator')) {

					echo '<a href="'.site_url().'/admin/edit/?slug='.$slug.'"><img src="'. get_stylesheet_directory_uri().'/images/editIcon.png" class="editIconImg1"></a>';
					echo '<a href="'.site_url().'/admin/search/?slug='.$slug.'" class="archive-post" data-slug="'.esc_attr($slug).'"><img src="'. get_stylesheet_directory_uri().'/images/deleteIcon.png" class="editIconImg1" alt="Archive"></a>';

				}

				echo '<p class="ReviewBoardSecText">'.get_the_excerpt().'</p>
	    </div>';

				endwhile; 
				post_pagination($paged,$latest_posts->max_num_pages);
				else:
				echo "No posts found.";
				endif;
				echo '<hr class="borderbottomcolor">';
				wp_reset_postdata();
			}
		}

		?>
	</div>
</div>