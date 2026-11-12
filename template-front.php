<?php
/*
Template Name: Custom Front Page Template
*/
get_header();
?>

<div class="row search-box mobileview">
	<div class="col-md-8">
		<?php get_search_form(); ?>
	</div>
</div>

<div class="PoliciesSecMainDiv container-fluid">
	<div class="row">
		<?php get_sidebar(); ?>
		<div class="col-md-9">
			<div class="row post-listing">
				<?php 
				$policies = [
					[
						'title' => 'Health & Wellness',
						'desc' => 'Benefits focused on Physical & Mental health to promote overall well-being of employees & their families.',
						'img' => 'Health-Wellness-icon_with_bg.png',
						'link' => 'doctors-consultation-with-extended-medical-benefits/'
					],
					[
						'title' => 'Financial Wellbeing',
						'desc' => 'Benefits designed to drive tax efficiency, manage financial stability and reduce financial stress during exigencies',
						'img' => 'Financial-Wellbeing-icon_with_bg.png',
						'link' => 'personal-loans-advances/'
					],
					[
						'title' => 'Time Off',
						'desc' => 'Various types of leave entitlements required for rest, recharge, vacation & any other personal needs.',
						'img' => 'Time-Off-iicon_with_bg.png',
						'link' => 'leave-attendance-policy/'
					],
					[
						'title' => 'Other Workplace Benefits',
						'desc' => 'Benefits designed to support employees in balancing professional responsibilities while enhancing social well-being.',
						'img' => 'Worklife-Effectiveness-icon_with_bg.png',
						'link' => 'employee-bonding-policy/'
					],
					[
						'title' => 'Career Advancement Opportunities',
						'desc' => 'Avenues for Skill enhancement & Career growth to help employees reach their Professional Aspirations.',
						'img' => 'Career-Advancement-Opportunities_with_bg.png',
						'link' => 'education-assistance-program/'
					],
					[
						'title' => 'Workplace Conduct & Compliances',
						'desc' => "Guidelines & Policies that ensure a respectful, ethical & safe work environment to foster inclusivity, protect employees' rights & mutual respect.",
						'img' => 'Workplace-Conduct-Compliances-icon_with_bg.png',
						'link' => 'prevention-deterrence-and-redressal-of-sexual-harassment-at-the-workplace/'
					]
				];

				foreach ($policies as $policy) : ?>
				<div class="col-md-4 mb-4">
					<div class="bg-sidebar1 m-0">
						<div class="policyImg-box">
							<img src="<?php echo site_url();?>/wp-content/uploads/sites/3/2025/01/<?= $policy['img']; ?>" alt="">
						</div>
						<p class="sidebar-title-post text-uppercase"> <?= $policy['title']; ?> </p>
						<p class="catdescription"> <?= $policy['desc']; ?> </p>
						<?php if (!empty($policy['link'])) : ?>
						<a class="except-more1" href="<?php echo site_url();?>/<?= $policy['link']; ?>" title="<?= $policy['title']; ?>">
							<div class="box-flip" title="Click to view Policies & Benefits under <?= $policy['title']; ?>">

							</div>  
						</a>
						<?php endif; ?>
					</div>
				</div>
				<?php endforeach; ?>
			</div>

			<div class="blog-article1"></div>
			<div class="btn__wrapper">
				<?php post_pagination($paged, $latest_posts->max_num_pages); ?>
			</div>
		</div>
	</div>
</div>

<?php get_footer(); ?>
