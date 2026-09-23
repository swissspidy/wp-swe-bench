<?php
/**
 * Courses of one topic (classic themes).
 *
 * @package Acme\Courses
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="primary" class="site-main acme-courses-main">
	<header class="acme-courses-archive__header">
		<h1 class="acme-courses-archive__title"><?php single_term_title(); ?></h1>
		<?php the_archive_description( '<div class="acme-courses-archive__description">', '</div>' ); ?>
	</header>

	<?php if ( have_posts() ) : ?>
		<ul class="acme-course-grid">
			<?php
			while ( have_posts() ) :
				the_post();
				acme_courses_get_template_part( 'course-card' );
			endwhile;
			?>
		</ul>
		<?php the_posts_pagination(); ?>
	<?php else : ?>
		<p class="acme-courses-empty"><?php esc_html_e( 'No courses in this topic yet.', 'acme-courses' ); ?></p>
	<?php endif; ?>
</main>
<?php
get_footer();
