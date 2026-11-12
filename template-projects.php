<?php
/*
Template Name: Custom Project Page Template
*/
get_header();
?>
<style>
	.table>thead>tr>th {
		font-size: 12px;
	}
	.m-4{
		margin-bottom:40px
	}
</style>
<!--Policies Section-->
<div class="PoliciesSecMainDiv container-fluid">   
	<div class="row">
		<div class="col-md-12 text-right m-4">
			<a href="add" class="btn btn-primary">Add Project <span class="dashicons dashicons-insert"></span></a>
		</div>
	</div>
	<div class="row post-listing">
		<div class="col-md-12">
			<?php
			global $post;          
			$paged = get_query_var('page') ? get_query_var('page') : 1;

			$latest_posts_args = array(
				'post_type' => 'project',
				'post_status' => array('publish'),           
				"orderby" => "post_modified",
				'paged' => $paged,
				'order'=>'DESC',
			);         
			$latest_posts_args['posts_per_page']  = 9;         
			$latest_posts = new WP_Query($latest_posts_args);


			?>

			<table cellpadding="20" cellspacing="10" border=1 class="table table-responsive">
				<thead>
					<tr>
						<th>Sr. No.</th>
						<th>Area</th>
						<th>Project</th>
						<th>Stakeholder</th>
						<th>Project Lead</th>
						<th>TGIT Lead</th>
						<th>Nature</th>
						<th>Category</th>
						<th>Modules Impact</th>
						<th>Resource Deployment</th>
						<th>Resource Requirement</th>
						<th>Priority</th>
						<th>Project Desctiption</th>
						<th>Current Status</th>
						<th>Go Live Schedule/Readiness</th>
						<th>Remarks</th>
					</tr>
				</thead>
				<tbody>

					<?php
					if(isset($latest_posts->posts) && !empty($latest_posts->posts) && is_array($latest_posts->posts)){
						$x =0;
						foreach($latest_posts->posts as $project){
							$x++;
							$project_id = $project->ID;

							echo "<tr>";
							echo "<td>".$x."</td>";
							echo "<td>".get_post_meta($project_id,'area',true)."</td>";
							echo "<td>".$project->post_title."</td>";
							echo "<td>".get_post_meta($project_id,'stakeholder',true)."</td>";
							echo "<td>".get_post_meta($project_id,'project_lead',true)."</td>";
							echo "<td>".get_post_meta($project_id,'tgit_lead',true)."</td>";
							echo "<td>".get_post_meta($project_id,'nature',true)."</td>";
							echo "<td>".get_post_meta($project_id,'category',true)."</td>";
							echo "<td>".get_post_meta($project_id,'modules_impact',true)."</td>";
							echo "<td>".get_post_meta($project_id,'resource_deployment',true)."</td>";
							echo "<td>".get_post_meta($project_id,'resource_requirement',true)."</td>";
							echo "<td>".get_post_meta($project_id,'priority',true)."</td>";
							echo "<td>".get_post_meta($project_id,'project_desctiption',true)."</td>";
							echo "<td>".get_post_meta($project_id,'current_status',true)."</td>";
							echo "<td>".get_post_meta($project_id,'go_live_schedule',true)."</td>";
							echo "<td>".get_post_meta($project_id,'remarks',true)."</td>";
							echo "</tr>";

						}
					}

					?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<?php get_footer();?>