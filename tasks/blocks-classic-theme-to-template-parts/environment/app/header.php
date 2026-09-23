<?php
/**
 * The header: <head>, skip link, site branding, primary navigation, header button.
 *
 * @package Acme_Corporate
 */

defined( 'ABSPATH' ) || exit;
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div id="page" class="site">
	<a class="skip-link screen-reader-text" href="#primary"><?php esc_html_e( 'Skip to content', 'acme-corporate' ); ?></a>

	<header id="masthead" class="site-header">
		<div class="site-header__inner">
			<?php acme_corporate_site_branding(); ?>

			<nav id="site-navigation" class="main-navigation" aria-label="<?php esc_attr_e( 'Primary', 'acme-corporate' ); ?>">
				<button class="menu-toggle" aria-controls="primary-menu" aria-expanded="false"><?php esc_html_e( 'Menu', 'acme-corporate' ); ?></button>
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'primary',
						'menu_id'        => 'primary-menu',
						'container'      => false,
						'walker'         => new Acme_Corporate_Menu_Walker(),
						'fallback_cb'    => 'acme_corporate_menu_fallback',
					)
				);
				?>
			</nav>

			<?php acme_corporate_header_cta(); ?>
		</div>
	</header>
