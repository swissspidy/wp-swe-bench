<?php
/**
 * Single course (classic themes).
 *
 * Override by copying to your theme as single-acme_course.php or
 * acme-courses/single-acme_course.php.
 *
 * @package Acme\Courses
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="primary" class="site-main acme-courses-main">
	<?php
	while ( have_posts() ) :
		the_post();
		?>
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'acme-course' ); ?>>
			<header class="acme-course__header">
				<?php the_title( '<h1 class="acme-course__title">', '</h1>' ); ?>
				<?php if ( has_post_thumbnail() ) : ?>
					<div class="acme-course__image"><?php the_post_thumbnail( 'large' ); ?></div>
				<?php endif; ?>
			</header>

			<div class="acme-course__content">
				<?php the_content(); ?>
			</div>

			<footer class="acme-course__footer">
				<p class="acme-course__topics">
					<span><?php esc_html_e( 'Topics:', 'acme-courses' ); ?></span>
					<?php acme_course_topics(); ?>
				</p>
			</footer>
		</article>
		<?php
	endwhile;
	?>
</main>
<?php
get_footer();
