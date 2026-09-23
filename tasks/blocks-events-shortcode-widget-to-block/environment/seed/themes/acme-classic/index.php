<?php
/**
 * Main template.
 *
 * @package AcmeClassic
 */

get_header();
?>
<main id="primary" class="site-main">
	<?php
	while ( have_posts() ) :
		the_post();
		?>
		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<?php if ( is_singular() ) : ?>
				<h1 class="entry-title"><?php the_title(); ?></h1>
			<?php else : ?>
				<h2 class="entry-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
			<?php endif; ?>
			<div class="entry-content"><?php the_content(); ?></div>
		</article>
		<?php
	endwhile;
	?>
</main>
<?php
get_sidebar();
get_footer();
