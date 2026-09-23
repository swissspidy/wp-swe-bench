<?php
/**
 * Fallback template.
 *
 * @package Acme_Classic
 */

get_header();
?>
<main id="primary" class="site-main">
	<?php
	if ( have_posts() ) {
		while ( have_posts() ) {
			the_post();
			echo '<article class="acme-classic-entry">';
			the_title( '<h2 class="acme-classic-entry__title"><a href="' . esc_url( get_permalink() ) . '">', '</a></h2>' );
			if ( is_singular() ) {
				the_content();
			} else {
				the_excerpt();
			}
			echo '</article>';
		}
	} else {
		echo '<p>Nothing found.</p>';
	}
	?>
</main>
<?php
get_footer();
