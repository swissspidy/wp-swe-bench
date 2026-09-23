<?php
/**
 * Main template.
 *
 * @package AcmeClassic
 */

get_header();

if ( have_posts() ) :
	while ( have_posts() ) :
		the_post();
		?>
		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<?php if ( is_singular() ) : ?>
				<h1 class="entry-title"><?php the_title(); ?></h1>
			<?php else : ?>
				<h2 class="entry-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
			<?php endif; ?>
			<div class="entry-content">
				<?php is_singular() ? the_content() : the_excerpt(); ?>
			</div>
		</article>
		<?php
	endwhile;
	the_posts_pagination();
else :
	echo '<p>' . esc_html__( 'Nothing found.', 'acme-classic' ) . '</p>';
endif;

get_footer();
