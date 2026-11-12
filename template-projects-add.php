<?php
/*
Template Name: Custom Project Add Template
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
	.container {
		width: 1200px;
		margin: 0px auto;
	}
</style>
<?php

// echo "<pre>";
// print_r($_REQUEST);
// echo "</pre>";

?>
<!--Policies Section-->
<div class="PoliciesSecMainDiv container">   
	<div class="row post-listing">
		<div class="col-md-12">
			<div class="card" style="width: 100%;margin:0 auto">
				<form class="projects_add" action="" method="post">
					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label for="area">Area</label>
								<input type="text" name="area" id="area" class="form-control">
							</div>
						</div>
						<div class="col-md-6">
							<div class="form-group">
								<label for="project">Project <span class="text-danger">*</span></label>
								<input type="text" name="project" id="project" class="form-control" required>
							</div>
						</div>
					</div>
					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label for="stakeholder">Stakeholder</label>
								<select type="text" name="stakeholder" id="stakeholder" class="form-control">>
									<option>Option1</option>
									<option>Option1</option>
									<option>Option1</option>
								</select>
							</div>
						</div>
						<div class="col-md-6">
							<div class="form-group">
								<label for="project_lead">Project Lead</label>
								<select type="text" name="project_lead" id="project_lead" class="form-control">
									<option>Option1</option>
									<option>Option1</option>
									<option>Option1</option>
								</select>
							</div>
						</div>
					</div>
					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label for="tgit_lead">TGIT Lead</label>
								<select type="text" name="tgit_lead" id="tgit_lead" class="form-control">
									<option>Option1</option>
									<option>Option1</option>
									<option>Option1</option>
								</select>
							</div>
						</div>
						<div class="col-md-6">
							<div class="form-group">
								<label for="nature">Nature</label>
								<input type="text" name="nature" id="nature" class="form-control">
							</div>
						</div>
					</div>
					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label for="category">Category</label>
								<input type="text" name="category" id="category" class="form-control">
							</div>
						</div>
						<div class="col-md-6">
							<div class="form-group">
								<label for="modules_impact">Modules Impact</label>
								<input type="text" name="modules_impact" id="modules_impact" class="form-control">
							</div>
						</div>
					</div>

					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label for="resource_deployment">Resource Deployment</label>
								<input type="text" name="resource_deployment" id="resource_deployment" class="form-control">
							</div>
						</div>
						<div class="col-md-6">
							<div class="form-group">
								<label for="resource_requirement">Resource Requirement</label>
								<input type="text" name="resource_requirement" id="resource_requirement" class="form-control">
							</div>
						</div>
					</div>

					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label for="priority">Priority</label>
								<input type="text" name="priority" id="priority" class="form-control">
							</div>
						</div>
						<div class="col-md-6">

							<div class="form-group">
								<label for="project_desctiption">Project Desctiption</label>
								<input type="text" name="project_desctiption" id="project_desctiption" class="form-control">
							</div>
						</div>
					</div>
					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label for="project_desctiption">Current Status</label>
								<input type="text" name="project_desctiption" id="project_desctiption" class="form-control">
							</div>
						</div>
						<div class="col-md-6">
							<div class="form-group">
								<label for="go_live">Go Live Schedule/Readiness</label>
								<input type="text" name="go_live" id="go_live" class="form-control">
							</div>
						</div>
					</div>
					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label for="remarks">Remarks</label>
								<input type="text" name="remarks" id="remarks" class="form-control">
							</div>
						</div>
						<div class="col-md-6">
						</div>
					</div>
					<div class="form-group">
						<button type="submit" id="project_add" class="btn btn-primary">Submit</button>
					</div>					
				</form>
			</div>
		</div>
	</div>
</div>

<?php get_footer();?>

<script>
	jQuery(document).on('click','#project_add',function(e){
		e.preventDefault();
		let projects_form = jQuery('.projects_add').serialize();
		jQuery.ajax({
			type: "POST",
			url: my_ajax_object.ajax_url,
			data: { action: "project_add_new", projects_form: projects_form },
			success: function (response) {
				//console.log(response);
				if (response.type == "success") {
					alert("Comment approved successfully!");
					
				}
				else {
					alert("Something went wrong.");
				}
			}
		})
	});
</script>