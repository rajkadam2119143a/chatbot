<?php
/**
 * The template for displaying the footer
 *
 * Contains the opening of the #site-footer div and all content after.
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package WordPress
 * @subpackage Twenty_Twenty
 * @since Twenty Twenty 1.0
 */

?>
			<footer id="site-footer" class="text-center">
				<p class="footer-copyright">Copyright &copy;
					<?php
					echo date_i18n(
						_x( 'Y', 'copyright date format', 'policies-child' )
					);
					?>.Bennett.Coleman and Co.Ltd.
				</p><!-- .footer-copyright -->

				<div id="scroll-to-top" class="topbutton">
					<i class="fa fa-chevron-up" aria-hidden="true"></i>
				</div>

			</footer><!-- #site-footer -->

		<?php wp_footer(); ?>

	</body>
</html>
