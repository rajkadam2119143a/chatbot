<?php
/* Template Name: Custom Archive Template */
/**
 * The template for displaying archive pages
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package Dosth
 */
get_header();
?>

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
                  			<li><a href="<?php site_url(); ?>/bccl/tools_templates/admin/archives" class="<?php if (is_page('archives')) echo 'ActivepolicyHeadingNav'; ?>">Archives</a></li>
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

               <form id="new_post" name="new_post" method="post" >
			      <?php
			      $str = rand();
			      $result = md5($str);

			      $_SESSION['hidden_admin'] = $result;

			      ?>
			    <input type="hidden" value="<?php echo $result; ?>" name="hidden-1">

			    <div class="row formarginTop">
			    	<div class="col-md-6">
			    		<label for="category" class="formLabel">Category</label>
					    <?php wp_dropdown_categories(array(
					            'taxonomy' => 'category',
					            'hierarchical' => 1,
					            'show_option_none' => "Select",
					            'option_none_value' => "",
					            'name' => 'category',
					            'id' => 'cat_search',
					            'class' => 'form-control selectControl',
					            'value_field' => 'term_id',
					            'exclude'  => 1
					        ));
					    ?>
			    	</div>
			    	<div class="col-md-6 MarginTopSec">
                    	<label for="year" class="formLabel">Year</label>
			            <select name="year" id="year" class="form-control selectControl" role="menu" >
	                    	<option value="">Select</option>
	                    	<?php
	                    	wp_get_archives( array(
	                    		'type' => 'yearly', 
	                    		'format' => 'custom', 
	                    		'class' => 'form-control selectControl',
	                    	));
	                    	?>
	                    </select>
                    </div>
			    </div>

			    <div class="read-more-box formarginTop">
			      <span class="except-more">
			      	<img src="<?php echo get_stylesheet_directory_uri(); ?>/images/redArrow.png" class="Redarraowimg">
			      	<input type="submit" class="btnCss" value="Search" id="submit" name="submit" />
			      </span>
			    </div>
			    
			    </form>

            </div>
         </div>
      </div>


	<div class="ReviewBoardSec">
	    <div class="ReviewBoradData formarginTop2" >
			<?php  
			if(isset($_POST['year']) && isset($_POST['hidden-1']) || isset($_POST['category']) && isset($_POST['hidden-1'])) {
				//echo $_POST['year'];
			    $paged = ( get_query_var( 'paged' ) ) ? absint( get_query_var( 'paged' ) ) : 1;

			    // Add the content of the form to $post as an array
			    $args = array(
			        'post_type'   => 'post',
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


			    if(isset($_POST['category']) && $_POST['category'] != '') {
			      $args['category__in'] = array( $_POST['category'] );
			    }

			    if(isset($_POST['year']) && $_POST['year'] != '') {
			      $args['date_query'] = array(
											array(
												'year' => (int)$_POST['year'],
											),
										);
			    }

			    //echo "<pre>";
			    //print_r($args);die;
			    $latest_posts = new WP_Query($args);
			    

			    while ($latest_posts->have_posts()) :
			          $latest_posts->the_post();
			          $slug = get_post_field( 'post_name', get_post() );

				    echo '<div class="formarginTop">
				        <span class="ReviewBoardSecHeadingText"><a href="'.get_the_permalink(get_the_ID()).'">'.get_the_title().'</a></span>
				        <p class="ReviewBoardSecText1">'.strtoupper(get_the_date( get_option('date_format'),get_the_ID())).'</p>';

				    if (current_user_can('policy-admin') || current_user_can('administrator')) {
				    	echo '<a href="'.site_url().'/admin/archives/?slug='.$slug.'" class="restore-post" data-slug="'.esc_attr($slug).'">Restore</a>';
				    }

				    echo '</div>';

			    endwhile; 
			    
			}
			?>
		</div>       
	    <?php
	    //if(isset($latest_posts->max_num_pages)) {
	    //	post_pagination($paged,$latest_posts->max_num_pages); 
		//}
	    ?>
	    <hr>
	</div>
	<?php //wp_reset_postdata(); ?>
	</div>
</div>


<?php get_footer(); ?>