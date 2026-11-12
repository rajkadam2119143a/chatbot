<?php
/* Template Name: Custom Category */
/**
 * The template for displaying archive pages
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package Dosth
 */
get_header();

// Handle form submission
if (isset($_POST['submit_category'])) {
	$category_name = sanitize_text_field($_POST['category_name']);
	$category_slug = sanitize_text_field($_POST['category_slug']);
	$category_parent = intval($_POST['category_parent']);
	$category_description = sanitize_text_field($_POST['category_description']);

	$result = wp_insert_term(
		$category_name,
		'category',
		array(
			'description' => $category_description,
			'slug' => $category_slug,
			'parent' => $category_parent
		)
	);

	if (is_wp_error($result)) {
		$error_message = $result->get_error_message();
		echo "<div class='alert alert-danger'>$error_message</div>";
	} else {
		echo "<div class='alert alert-success'>Category added successfully!</div>";
	}
}
if (isset($_POST['edit_category'])) {
	// Prepare the data to update
	$term_data = array(
		'name' => sanitize_text_field($_POST['category_name']),
		'parent' => intval($_POST['category_parent'])
	);
	$term_id = sanitize_text_field($_POST['tag_id']);
	// Update the category
	$result = wp_update_term($term_id, 'category', $term_data);

	// Check for errors
	if (is_wp_error($result)) {
		$error_message = $result->get_error_message();
		echo "<div class='alert alert-danger'>$error_message</div>";
	} else {
		echo "<div class='alert alert-success'>Category updated successfully!</div>";
	}
}

if (isset($_GET['action']) && !empty($_GET['action']) && $_GET['action'] == 'delete') {
	// Check if the category exists
	if (get_category($_GET['tag_ID'])) {
		// Delete the category
		$result = wp_delete_category($_GET['tag_ID']);

		if ($result) {
			echo "<div class='alert alert-success'>Category deleted successfully!</div>";
		} else {
			echo "<div class='alert alert-danger'>Category deletion failed.</div>";
		}
	} else {
		echo "<div class='alert alert-danger'>Category does not exist.</div>";
	}
?>
<script>
	jQuery(document).ready(function() {
		// Get the current URL
		var currentUrl = window.location.href;

		// Remove query parameters
		var newUrl = currentUrl.split('?')[0];

		// Update the browser's history without reloading the page
		if (currentUrl !== newUrl) {
			history.replaceState(null, '', newUrl);
		}
	});
</script>
<?php
}
if (isset($_GET['action']) && !empty($_GET['action']) && $_GET['action'] == 'edit') {
	// Replace with your term ID and taxonomy
	$term_id = $_GET['tag_ID']; // Example term ID
	$taxonomy = $_GET['taxonomy']; // Example taxonomy, can be 'category', 'post_tag', or any custom taxonomy
	// Retrieve the term
	$term = get_term($term_id, $taxonomy);
	// Check if the term exists and is not an error
	if (!is_wp_error($term) && $term) {
		// Output term data
		$tag_id = $term->name;
		$tag_name = $term->name;
		$tag_parent = $term->parent;
	} else {
		$tag_id = '';
		$tag_name = '';
		$tag_parent = '';
	}
}
?>
<?php if(hr_user_can_manage_policies()) { ?>
<div class="row policyHeadingText">
	<div class="col-md-2 navsideBarSec">
		<?php echo get_template_part('template-parts/content', 'sidebar'); ?>
	</div>
	<!-- tab content -->
	<div class="col-md-10">
		<div class="row">
			<div class="col-md-3">			
				<form action="<?php echo site_url().'/admin/addcategory/'; ?>" method="POST">
					<div class="card bg-sidebar mx-0 border-0 p-0">
						<div class="card-header">
							<?php echo (isset($_GET['action']) && !empty($_GET['action']) && $_GET['action'] == 'edit') ? 'Edit Category' : 'Add New Category'; ?>
						</div>
						<div class="card-body">
							<div class="mb-3">
								<label for="category_name" class="form-label">Name</label>
								<input type="text" name="category_name" class="form-control" id="category_name" value="<?php echo (isset($_GET['action']) && !empty($_GET['action']) && $_GET['action'] == 'edit') ? $tag_id : ''; ?>"/>
							</div>
							<!-- 							<div class="mb-3">
<label for="category_slug" class="form-label">Slug</label>
<input type="text" name="category_slug" class="form-control" id="category_slug"/>
</div> -->
							<div class="">
								<label for="category_parent" class="form-label">Parent Category</label>
								<?php
										  wp_dropdown_categories(array(
											  'show_option_none' => 'Select Category', // Placeholder option
											  'name'            => 'category_parent',   // The name attribute for the select element
											  'class'           => 'form-control postform', // CSS class for styling
											  'hide_empty'      => false, // Show all categories, including those without posts
											  'hierarchical'    => true, // Display categories hierarchically
											  'show_count'      => false, // Optional: Show the number of posts in each category
											  'echo'            => 1, // Print the dropdown directly
											  'selected'        => isset($tag_parent) ? $tag_parent : '', // Default selected category
										  ));
								?>
							</div>
						</div>
						<div class="card-footer bg-white py-3">
							<?php
										  if (isset($_GET['action']) && !empty($_GET['action']) && $_GET['action'] == 'edit') {
							?>
							<input type="hidden" name="tag_id" value="<?php echo isset($_GET['tag_ID']) ? $_GET['tag_ID'] : ''?>"/> 
							<button type="submit" class="btn btn-primary m-0" style="font-size:12px" name="edit_category">
								Edit Category
							</button>
							<?php
										  }else{
							?>
							<button type="submit" class="btn btn-primary m-0" style="font-size:12px" name="submit_category">
								Add New Category
							</button>
							<?php
										  }
							?>

						</div>
					</div>
				</form>
			</div>
			<div class="col-md-9">
				<div class="card bg-sidebar mx-0 border-0 p-0">
					<div class="card-header">
						Categories
					</div>
					<?php
										  if (hr_user_can_manage_policies()) {
											  $categories = get_categories(array(
												  'orderby' => 'name',
												  'order' => 'ASC',
												  'hide_empty' => false,
												  'parent' => 0
											  ));

											  function display_category_row($category, $depth = 0)
											  {
												  $indent = str_repeat('&mdash; ', $depth);
					?>
					<tr>
						<td class="column-primary">
							<strong><?php echo $indent . esc_html($category->name); ?></strong>
							<div class="row-actions">
								<span class="edit"><a href="<?php echo get_permalink(983) . '?taxonomy=' . $category->taxonomy . '&tag_ID=' . $category->term_id . '&action=edit'; ?>">Edit</a></span>
								<span class="delete"><a class="text-danger" href="<?php echo get_permalink(983) . '?taxonomy=' . $category->taxonomy . '&tag_ID=' . $category->term_id . '&action=delete'; ?>">Delete</a></span>
							</div>
						</td>
						<td><?php echo esc_html($category->slug); ?></td>
						<td><?php echo esc_html($category->count); ?></td>
					</tr>
					<?php
												  $child_categories = get_categories(array(
													  'orderby' => 'name',
													  'order' => 'ASC',
													  'hide_empty' => false,
													  'parent' => $category->term_id
												  ));
												  foreach ($child_categories as $child_category) {
													  display_category_row($child_category, $depth + 1);
												  }
											  }
					?>
					<table class="table table-hover" style="font-size:13px">
						<thead>
							<tr>
								<th scope="col" id="name" class="manage-column column-name column-primary text-start">Name</th>
								<th scope="col" id="slug" class="manage-column column-slug text-start">Slug</th>
								<th scope="col" id="posts" class="manage-column column-posts text-start">Posts</th>
							</tr>
						</thead>
						<tbody>
							<?php
											  foreach ($categories as $category) {
												  display_category_row($category);
											  }
							?>
						</tbody>
					</table>
					<?php
										  } else {
											  echo '<p>You do not have access to this page.</p>';
										  }
					?>
				</div>
			</div>
		</div>		
	</div>
</div>
<?php }else {?>
<p>You do not have access to this page.</p>
<?php } ?>
<?php get_footer(); ?>