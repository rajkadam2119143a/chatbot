<div class="bg-sidebar m-2">
	<span class="sidebar-title-postHeading">Admin Panel</span>
	<div class="BorderBottom"></div>
	<div class="policySectionHeight scrollbar">
		<div class="sidebar-menu-post2">
			<div class="menu-home-page-menu-container">
				<div class="panel-group" id="accordion">
					<div class="panel-default">
						<ul>
							<li><a href="<?php echo get_permalink(7); ?>" class="<?php if (is_page('add')) echo 'ActivepolicyHeadingNav'; ?>">Add New Policy</a></li>
							<li><a href="<?php echo get_permalink(13); ?>" class="<?php if (is_page('search') || is_page('edit')) echo 'ActivepolicyHeadingNav'; ?>">Edit/Search Policy</a></li>
							<li><a href="<?php echo get_permalink(9); ?>" class="<?php if (is_page('archives')) echo 'ActivepolicyHeadingNav'; ?>">Archives</a></li>
							<li><a href="<?php echo get_permalink(983); ?>" class="<?php if (is_page('addcategory')) echo 'ActivepolicyHeadingNav'; ?>">Category</a></li>
						</ul>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>