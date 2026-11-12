<!DOCTYPE html>
<html <?php language_attributes(); ?>>

	<head>
		<meta charset="UTF-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<link href='https://fonts.googleapis.com/css?family=Open Sans' rel='stylesheet'>
		<!-- Google tag (gtag.js) -->
		<script async src="https://www.googletagmanager.com/gtag/js?id=G-3VBJ4XTRM3"></script> 
		<script>   window.dataLayer = window.dataLayer || [];   function gtag(){dataLayer.push(arguments);}   gtag('js', new Date());   gtag('config', 'G-3VBJ4XTRM3'); </script>
		<title>HR Policies & Guidelines</title>
		<?php wp_head(); ?>

		<style>
	        header {
		background: url('<?php echo esc_url( wp_upload_dir()['baseurl'] . '/2025/01/banner_02.jpg' ); ?>') no-repeat center center;
		background-size: cover;
		width: 100%;
		padding: 10px 10px 10px 10px;
	        }
			.PolicysliderWitHeadingDiv.desktopview {
				display: grid;
				grid-template-columns: 60% auto;
				align-items: center;
				justify-content: space-between;

			}

			.actionbutton {
				display: flex;
				justify-content: space-around;
				align-items: center;
				flex-direction: row;
				gap: 15px
			}

			.subcategory-section .sidebar-title-postHeading,.category-section .sidebar-title-postHeading{
				color: #004e62;
			}
			.category-section .category-section .sidebar-title-postHeading{
				color: #333;
			}
			@media only screen and (max-width: 767px) {
				.PolicysliderWitHeadingDiv {
					display: grid;
					grid-template-columns: 50% auto;
					align-items: center;
				}

				.ITPoliciesHeadingImg {
					width: 32%;
					text-align: center;
				}

				.actionbutton {
					justify-content: flex-end;
				}
				.ITPoliciesHeading h1{
					margin-bottom: 0px;
					margin: 0px;
					padding: 0px;
				}
				.main-search{
					margin: 0px;
				}
			}

		</style>

	</head>

	<body <?php body_class(); ?>>

		<header>
			<!-- 
<div class="backgroundImgDiv">
<img src="<?php echo get_stylesheet_directory_uri(); ?>/images/header_img2.jpg" class="bannerImg">
</div> -->

			<?php if (hr_user_can_manage_policies()) { ?>
			<div class="PolicysliderWitHeadingDiv desktopview">
				<div class="ITPoliciesHeading1">
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>">
						<h1>HR Policies & Guidelines</h1>
					</a>
				</div>
				<div class="actionbutton">
					<div class="container1 SearchDiv ">
						<form class="searchbox " action="<?php echo esc_url(home_url('/')); ?>">
							<input type="text" placeholder="Search" id="search-form" name="s" value="<?php echo (isset($_GET['s']) && !empty($_GET['s'])) ? $_GET['s'] : ''; ?>" class=" search-query ui-autocomplete-input">
							<button href="#" class="home-sch-ic  searchGrayIcon" type="submit">
								<img src="<?php echo get_stylesheet_directory_uri(); ?>/images/searchicon.png">
							</button>
							<!-- <span class="searchbox-icon SearchIconDiv">
<a href="#" class="" id="search-icon">
<img src="<?php echo get_stylesheet_directory_uri(); ?>/images/searchWhite.png" style="text-align:right">
</a>
</span> -->
						</form>

					</div>

					<div class="ITPoliciesHeadingImg">
						<a href="<?php echo esc_url( home_url( '/' ) ); ?>">
							<img src="<?php echo get_stylesheet_directory_uri(); ?>/images/Home.png" class="homeIconImg">
						</a>
						<span class="ITPoliciesHeadingText">Home</span>
					</div>
					<div class="ITPoliciesHeadingImg  AdminSectionDiv">
						<a href="<?php echo esc_url( home_url( '/admin/add' ) ); ?>" class="desktopview">
							<img src="<?php echo get_stylesheet_directory_uri(); ?>/images/Admin.png" class="homeIconImg">
						</a>
						<img src="<?php echo get_stylesheet_directory_uri(); ?>/images/Admin.png" class="homeIconImg mobileview">
						<span class="ITPoliciesHeadingText">Admin</span>

						<div class="tooltiptext">
							<p><a href="<?php echo esc_url( home_url( '/admin/add' ) ); ?>" class="ActivepolicyHeadingNav aTagColor">Add Policy</a></p>
							<p><a href="<?php echo esc_url( home_url( '/admin/search' ) ); ?>" class="aTagColor">Edit/Search Policy</a></p>
							<p><a href="<?php echo esc_url( home_url( '/admin/archives' ) ); ?>" class="aTagColor">Archives</a></p>
						</div>
					</div>
				</div>
			</div>
			<?php } else { ?>

			<div class="PolicysliderWitHeadingDiv desktopview">
				<div class="ITPoliciesHeading1">
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>">
						<h1>HR Policies & Guidelines</h1>
					</a>
				</div>
				<div class="actionbutton">
					<div class="container1 SearchDiv ">
						<form class="searchbox " action="<?php echo esc_url(home_url('/')); ?>">
							<input type="text" placeholder="Search" id="search-form" name="s" value="<?php echo (isset($_GET['s']) && !empty($_GET['s'])) ? $_GET['s'] : ''; ?>" class=" search-query ui-autocomplete-input">
							<button href="#" class="home-sch-ic  searchGrayIcon" type="submit">
								<img src="<?php echo get_stylesheet_directory_uri(); ?>/images/searchicon.png">
							</button>
							<!-- 							<span class="searchbox-icon SearchIconDiv">
<a href="#" class="" id="search-icon">
<img src="<?php echo get_stylesheet_directory_uri(); ?>/images/searchWhite.png" style="text-align:right">
</a>
</span> -->
						</form>

					</div>

					<div class="ITPoliciesHeadingImg">
						<a href="<?php echo esc_url( home_url( '/' ) ); ?>">
							<img src="<?php echo get_stylesheet_directory_uri(); ?>/images/Home.png" class="homeIconImg">
						</a>
						<span class="ITPoliciesHeadingText">Home</span>
					</div>



					<div class="ITPoliciesHeadingImg  AdminSectionDiv">
						<a href="<?php echo esc_url( home_url( '/admin/add' ) ); ?>" class="desktopview">
							<img src="<?php echo get_stylesheet_directory_uri(); ?>/images/Admin.png" class="homeIconImg">
						</a>
						<img src="<?php echo get_stylesheet_directory_uri(); ?>/images/Admin.png" class="homeIconImg mobileview">
						<span class="ITPoliciesHeadingText">Admin</span>

						<div class="tooltiptext">
							<p><a href="<?php echo esc_url( home_url( '/admin/add' ) ); ?>" class="ActivepolicyHeadingNav aTagColor">Add Policy</a></p>
							<p><a href="<?php echo esc_url( home_url( '/admin/search' ) ); ?>" class="aTagColor">Edit/Search Policy</a></p>
							<p><a href="<?php echo esc_url( home_url( '/admin/archives' ) ); ?>" class="aTagColor">Archives</a></p>
						</div>
					</div>

				</div>
			</div>
			<?php } ?>


			<?php if (hr_user_can_manage_policies()) { ?>
			<div class="PolicysliderWitHeadingDiv mobileview">
				<div class="ITPoliciesHeading">
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>">
						<h1>Hr Policies</h1>

					</a>
				</div>
				<div class="actionbutton">
					<div class="ITPoliciesHeadingImg">
						<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><img src="<?php echo get_stylesheet_directory_uri(); ?>/images/Home.png" class="homeIconImg"></a>
						<span class="ITPoliciesHeadingText">Home</span>
					</div>

					<div class="ITPoliciesHeadingImg AdminSectionDiv">
						<a href="<?php echo esc_url( home_url( '/admin/add' ) ); ?>" class="desktopview"><img src="<?php echo get_stylesheet_directory_uri(); ?>/images/Admin.png" class="homeIconImg"></a>
						<img src="<?php echo get_stylesheet_directory_uri(); ?>/images/Admin.png" class="homeIconImg mobileview">
						<span class="ITPoliciesHeadingText">Admin</span>
						<div class="tooltiptext">
							<p><a href="<?php echo esc_url( home_url( '/admin/add' ) ); ?>" class="aTagColor">Add Policy</a></p>
							<p><a href="<?php echo esc_url( home_url( '/admin/search' ) ); ?>" class="ActivepolicyHeadingNav aTagColor">Edit/Search Policy</a></p>
							<p><a href="<?php echo esc_url( home_url( '/admin/archives' ) ); ?>" class="aTagColor">Archives</a></p>
						</div>
					</div>
				</div>
			</div>

			<?php } else { ?>

			<div class="PolicysliderWitHeadingDiv row mobileview">
				<div class="ITPoliciesHeading" style="width:86%">
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>">
						<h1> HR Policies & Guidelines</h1>
					</a>
				</div>
				<div class="actionbutton">
					<div class="ITPoliciesHeadingImg">
						<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><img src="<?php echo get_stylesheet_directory_uri(); ?>/images/Home.png" class="homeIconImg"></a>
						<span class="ITPoliciesHeadingText">Home</span>
					</div>

					<div class="ITPoliciesHeadingImg   AdminSectionDiv">
						<a href="<?php echo esc_url( home_url( '/admin/add' ) ); ?>" class="desktopview"><img src="<?php echo get_stylesheet_directory_uri(); ?>/images/Admin.png" class="homeIconImg"></a>
						<img src="<?php echo get_stylesheet_directory_uri(); ?>/images/Admin.png" class="homeIconImg mobileview">
						<span class="ITPoliciesHeadingText">Admin</span>
						<div class="tooltiptext">
							<p><a href="<?php echo esc_url( home_url( '/admin/add' ) ); ?>" class="aTagColor">Add Policy</a></p>
							<p><a href="<?php echo esc_url( home_url( '/admin/search' ) ); ?>" class="ActivepolicyHeadingNav aTagColor">Edit/Search Policy</a></p>
							<p><a href="<?php echo esc_url( home_url( '/admin/archives' ) ); ?>" class="aTagColor">Archives</a></p>
						</div>
					</div>
				</div>
			</div>

			<?php } ?>


		</header>