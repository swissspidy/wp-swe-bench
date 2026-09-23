<?php
/**
 * The footer: widgets, footer menu, contact details, social links, footer text.
 *
 * @package Acme_Corporate
 */

defined( 'ABSPATH' ) || exit;
?>
	<footer id="colophon" class="site-footer">
		<div class="site-footer__inner">
			<?php if ( is_active_sidebar( 'footer-1' ) ) : ?>
				<div class="footer-widgets">
					<?php dynamic_sidebar( 'footer-1' ); ?>
				</div>
			<?php endif; ?>

			<?php if ( has_nav_menu( 'footer' ) ) : ?>
				<nav class="footer-navigation" aria-label="<?php esc_attr_e( 'Footer', 'acme-corporate' ); ?>">
					<?php
					wp_nav_menu(
						array(
							'theme_location' => 'footer',
							'menu_id'        => 'footer-menu',
							'container'      => false,
							'depth'          => 1,
						)
					);
					?>
				</nav>
			<?php endif; ?>

			<div class="footer-contact">
				<?php acme_corporate_contact_details(); ?>
				<?php acme_corporate_social_links(); ?>
			</div>

			<div class="site-info">
				<?php acme_corporate_footer_text(); ?>
			</div>
		</div>
	</footer>
</div><!-- #page -->

<?php wp_footer(); ?>

</body>
</html>
