<?php
/**
 * Main template.
 *
 * @package Acme\Community
 */

get_header();
?>
<main id="primary">
	<?php if ( have_posts() ) : ?>
		<?php
		while ( have_posts() ) :
			the_post();
			?>
			<article <?php post_class(); ?>>
				<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
				<p class="byline"><?php the_author(); ?></p>
				<div class="entry-content"><?php the_content(); ?></div>
			</article>
		<?php endwhile; ?>
	<?php else : ?>
		<p><?php esc_html_e( 'Nothing found.', 'acme-community' ); ?></p>
	<?php endif; ?>
</main>
<?php
get_footer();
