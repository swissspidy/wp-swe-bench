<?php
/**
 * 404.
 *
 * @package Acme_Corporate
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="primary" class="site-main">
	<section class="error-404 not-found">
		<h1 class="page-title"><?php esc_html_e( 'Page not found', 'acme-corporate' ); ?></h1>
		<p><?php esc_html_e( 'The page you were looking for does not exist. Try a search:', 'acme-corporate' ); ?></p>
		<?php get_search_form(); ?>
	</section>
</main>
<?php
get_footer();
